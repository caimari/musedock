@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
.mcp-stat { display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;white-space:nowrap; }
.mcp-stat.on { background:rgba(25,135,84,0.1);border-color:rgba(25,135,84,0.2);color:#198754; }
.mcp-stat.off { background:rgba(108,117,125,0.08);border-color:rgba(108,117,125,0.2);color:#6c757d; }
.mcp-url { font-family:monospace;font-size:0.85rem;background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:0.45rem 0.75rem;word-break:break-all; }
.mcp-token { font-family:monospace;font-size:0.95rem;background:#fff;border:1px dashed #198754;border-radius:6px;padding:0.6rem 0.8rem;word-break:break-all; }
.mcp-snippet { position:relative; }
.mcp-snippet pre { background:#1e1e2e;color:#e6e6e6;border-radius:8px;padding:0.85rem 1rem;font-size:0.78rem;margin:0;white-space:pre-wrap;word-break:break-all; }
.mcp-snippet .btn-copy { position:absolute;top:0.4rem;right:0.4rem; }
.mcp-lvl { display:inline-block;font-size:0.68rem;padding:0.1rem 0.4rem;border-radius:4px;margin:0 0.15rem 0.15rem 0;font-weight:600; }
.mcp-lvl.read { background:#e7f1ff;color:#0d6efd; }
.mcp-lvl.write { background:#fff3cd;color:#997404; }
.mcp-lvl.publish { background:#d1e7dd;color:#146c43; }
.mcp-lvl.delete { background:#f8d7da;color:#b02a37; }
.mcp-matrix th, .mcp-matrix td { text-align:center;vertical-align:middle; }
.mcp-matrix th:first-child, .mcp-matrix td:first-child { text-align:left; }
.mcp-template { border:1px solid #e9ecef;border-radius:8px;padding:0.6rem 0.75rem;cursor:pointer;height:100%;transition:border-color .15s,background .15s; }
.mcp-template:hover { border-color:#adb5bd; }
.mcp-template input { margin-right:0.35rem; }
.mcp-template.active { border-color:#7c3aed;background:rgba(124,58,237,0.05); }
.mcp-template small { display:block;color:#6c757d;font-size:0.75rem;margin-top:0.2rem; }
.mcp-tabs .nav-link { font-size:0.85rem; }
</style>
@endpush

@section('content')
@php
  $activeKeys = array_filter($keys, fn($k) => $k['is_active'] && !$k['is_expired']);
  $tokenForSnippets = $newKey['raw'] ?? 'mdk_TU_TOKEN';
  $snippets = [
    'claude-ai' => [
      'label' => 'Claude.ai',
      'text'  => "1. En Claude.ai: Ajustes → Conectores → Añadir conector personalizado\n2. Nombre: {$GLOBALS['tenant']['name']}\n3. URL del servidor MCP: {$mcpUrl}\n4. Pulsa Conectar: se abrirá esta web para que inicies sesión y elijas los permisos.",
    ],
    'chatgpt' => [
      'label' => 'ChatGPT',
      'text'  => "1. En ChatGPT: Ajustes → Aplicaciones y conectores → Avanzado → activa el Modo desarrollador\n2. Crear conector → URL del servidor MCP: {$mcpUrl}\n3. Autenticación: OAuth\n4. Al conectar se abrirá esta web para que inicies sesión y elijas los permisos.",
    ],
    'claude-code' => [
      'label' => 'Claude Code',
      'text'  => "# Con OAuth (recomendado): añade el servidor y autentica con /mcp dentro de Claude Code\nclaude mcp add --transport http musedock {$mcpUrl}\n\n# O con token fijo:\nclaude mcp add --transport http musedock {$mcpUrl} \\\n  --header \"Authorization: Bearer {$tokenForSnippets}\"",
    ],
    'codex' => [
      'label' => 'Codex',
      'text'  => "# ~/.codex/config.toml\n[mcp_servers.musedock]\nurl = \"{$mcpUrl}\"\nbearer_token_env_var = \"MUSEDOCK_TOKEN\"\n\n# y en tu shell:\nexport MUSEDOCK_TOKEN=\"{$tokenForSnippets}\"",
    ],
    'desktop' => [
      'label' => 'Claude Desktop / Cursor',
      'text'  => json_encode(['mcpServers' => ['musedock' => [
          'command' => 'npx',
          'args' => ['-y', 'mcp-remote', $mcpUrl, '--header', 'Authorization:${AUTH_HEADER}'],
          'env' => ['AUTH_HEADER' => 'Bearer ' . $tokenForSnippets],
      ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ],
    'curl' => [
      'label' => 'Probar con curl',
      'text'  => "curl -s {$mcpUrl} \\\n  -H \"Authorization: Bearer {$tokenForSnippets}\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
    ],
  ];
  $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp

{{-- Header --}}
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div class="d-flex align-items-center gap-3">
    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#06b6d4);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bi bi-robot" style="font-size:1.35rem;color:#fff;"></i>
    </div>
    <div>
      <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Conecta tu sitio con asistentes de IA</h3>
      <p class="text-muted mb-0" style="font-size:0.85rem;">Claude Code, Codex y otros clientes MCP gestionan páginas y blog con los permisos que tú elijas.</p>
    </div>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <div class="mcp-stat {{ $enabled && $globalEnabled ? 'on' : 'off' }}">
      <i class="bi {{ $enabled && $globalEnabled ? 'bi-broadcast' : 'bi-pause-circle' }}"></i>
      <span>{{ $enabled && $globalEnabled ? 'MCP activo' : 'MCP desactivado' }}</span>
    </div>
    <div class="mcp-stat">
      <i class="bi bi-key"></i>
      <span>{{ count($activeKeys) }} {{ count($activeKeys) === 1 ? 'conexión activa' : 'conexiones activas' }}</span>
    </div>
    <a href="{{ $adminBase }}/settings/api-keys" class="mcp-stat" style="text-decoration:none;color:#7c3aed;border-color:rgba(124,58,237,0.2);background:rgba(124,58,237,0.08);">
      <i class="bi bi-code-slash"></i>
      <span>API REST</span>
    </a>
  </div>
</div>

@if(!$globalEnabled)
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> El administrador del servidor ha desactivado MCP globalmente (<code>MCP_ENABLED=false</code>). Las conexiones no funcionarán hasta que se reactive.</div>
@endif

{{-- Token recién creado --}}
@if($newKey)
  <div class="card border-success mb-4">
    <div class="card-body">
      <h5 class="text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Conexión "{{ $newKey['name'] }}" creada</h5>
      <p class="mb-2 small">Copia el token ahora. Por seguridad solo se guarda su huella y <strong>no se volverá a mostrar</strong>.</p>
      <div class="d-flex gap-2 align-items-center">
        <div class="mcp-token flex-grow-1" id="newToken">{{ $newKey['raw'] }}</div>
        <button type="button" class="btn btn-success btn-sm" data-copy-target="newToken"><i class="bi bi-clipboard"></i> Copiar</button>
      </div>
      <p class="small text-muted mt-2 mb-0">Los ejemplos de "Cómo conectar" de abajo ya incluyen este token.</p>
    </div>
  </div>
@endif

<div class="row g-4">
  {{-- Estado + URL --}}
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Servidor MCP del sitio</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ $adminBase }}/mcp/toggle" class="mb-3">
          {!! csrf_field() !!}
          <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong>{{ $enabled ? 'Activado' : 'Desactivado' }}</strong>
              <div class="small text-muted">{{ $enabled ? 'Las conexiones con token válido pueden acceder.' : 'Nadie puede acceder, aunque tenga token.' }}</div>
            </div>
            @if($canEdit)
              <button type="submit" class="btn btn-sm {{ $enabled ? 'btn-outline-danger' : 'btn-success' }}">
                <i class="bi {{ $enabled ? 'bi-power' : 'bi-play-fill' }}"></i> {{ $enabled ? 'Desactivar' : 'Activar' }}
              </button>
            @endif
          </div>
        </form>

        <label class="form-label small text-muted mb-1">URL del servidor MCP</label>
        <div class="d-flex gap-2 align-items-center">
          <div class="mcp-url flex-grow-1" id="mcpUrl">{{ $mcpUrl }}</div>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-copy-target="mcpUrl" title="Copiar"><i class="bi bi-clipboard"></i></button>
        </div>

        <ul class="small text-muted mt-3 mb-0 ps-3">
          <li>Cada token solo da acceso a <strong>este sitio</strong>.</li>
          <li>El HTML que envía la IA se limpia: sin scripts, formularios ni iframes desconocidos.</li>
          <li>Sin permiso <em>Publicar</em>, todo queda en borrador y no se puede tocar lo que ya está publicado.</li>
          <li>Borrar siempre pide confirmación al usuario y manda a la papelera.</li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Cómo conectar --}}
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Cómo conectar</h5></div>
      <div class="card-body">
        <ul class="nav nav-tabs mcp-tabs mb-3" role="tablist">
          @foreach($snippets as $sKey => $snippet)
            <li class="nav-item" role="presentation">
              <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#snip-{{ $sKey }}" type="button" role="tab">{{ $snippet['label'] }}</button>
            </li>
          @endforeach
        </ul>
        <div class="tab-content">
          @foreach($snippets as $sKey => $snippet)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="snip-{{ $sKey }}" role="tabpanel">
              <div class="mcp-snippet">
                <pre id="snipText-{{ $sKey }}">{{ $snippet['text'] }}</pre>
                <button type="button" class="btn btn-light btn-sm btn-copy" data-copy-target="snipText-{{ $sKey }}"><i class="bi bi-clipboard"></i></button>
              </div>
            </div>
          @endforeach
        </div>
        <p class="small text-muted mt-3 mb-0">
          <i class="bi bi-info-circle"></i> Claude.ai, ChatGPT y Claude Code se conectan con <strong>OAuth</strong>: inicias sesión aquí y eliges los permisos, sin copiar tokens. Aparecerán en <em>Conexiones</em>.
          @if(!$newKey) Para Codex, Cursor o scripts crea un token y sustituye <code>mdk_TU_TOKEN</code>. @endif
        </p>
      </div>
    </div>
  </div>
</div>

{{-- Conexiones --}}
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Conexiones</h5>
    @if($canEdit)
      <div class="d-flex gap-2">
        @if(!empty($keys))
          <form method="POST" action="{{ $adminBase }}/mcp/revoke-all" id="formRevokeAll" class="d-inline">
            {!! csrf_field() !!}
            <input type="hidden" name="confirm" value="">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-octagon"></i> Revocar todas</button>
          </form>
        @endif
        <button type="button" class="btn btn-primary btn-sm" id="btnNewConnection"><i class="bi bi-plus-lg"></i> Nueva conexión</button>
      </div>
    @endif
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Nombre</th>
            <th>Permisos</th>
            <th>Último uso</th>
            <th>Caduca</th>
            <th>Estado</th>
            <th class="text-end" style="width:170px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @if(empty($keys))
            <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay conexiones. Crea una para conectar tu asistente de IA.</td></tr>
          @endif
          @foreach($keys as $k)
            <tr>
              <td>
                <strong>{{ $k['name'] }}</strong>
                @if(($k['auth_type'] ?? 'token') === 'oauth')
                  <span class="badge bg-primary ms-1" title="Conectada con OAuth"><i class="bi bi-shield-check"></i> OAuth</span>
                @endif
                @if($k['template'])
                  <span class="badge bg-light text-dark border ms-1">{{ $templates[$k['template']]['label'] }}</span>
                @endif
                @if(($k['auth_type'] ?? 'token') === 'oauth')
                  <div class="small text-muted">
                    Autorizada{{ !empty($k['oauth_user_name']) ? ' por ' . $k['oauth_user_name'] : '' }}
                    @if(!empty($k['oauth_client_uri'])) · {{ parse_url($k['oauth_client_uri'], PHP_URL_HOST) }} @endif
                  </div>
                @else
                  <div class="small text-muted font-monospace">{{ substr($k['api_key_hash'], 0, 10) }}…</div>
                @endif
                @if(!empty($k['allowed_ips']))
                  <div class="small text-muted"><i class="bi bi-shield-lock"></i> {{ $k['allowed_ips'] }}</div>
                @endif
              </td>
              <td style="min-width:220px;">
                @forelse($k['matrix'] as $section => $sectionLevels)
                  <div class="small">
                    <span class="text-muted">{{ \Screenart\Musedock\Services\Mcp\McpPermissions::sectionLabel($section) }}:</span>
                    @foreach($sectionLevels as $lvl)<span class="mcp-lvl {{ $lvl }}">{{ $levels[$lvl] }}</span>@endforeach
                  </div>
                @empty
                  <span class="text-muted small">—</span>
                @endforelse
              </td>
              <td><small>{{ $k['last_used_at'] ? date('d/m/Y H:i', strtotime($k['last_used_at'])) : 'Nunca' }}</small></td>
              <td><small class="{{ $k['is_expired'] ? 'text-danger' : '' }}">{{ $k['expires_at'] ? date('d/m/Y', strtotime($k['expires_at'])) : 'Nunca' }}</small></td>
              <td>
                @if(!$k['is_active'])
                  <span class="badge bg-secondary">Inactiva</span>
                @elseif($k['is_expired'])
                  <span class="badge bg-danger">Caducada</span>
                @else
                  <span class="badge bg-success">Activa</span>
                @endif
              </td>
              <td class="text-end">
                @if($canEdit)
                  <button type="button" class="btn btn-outline-secondary btn-sm btn-edit-connection"
                          data-id="{{ $k['id'] }}"
                          data-name="{{ $k['name'] }}"
                          data-ips="{{ $k['allowed_ips'] ?? '' }}"
                          data-template="{{ $k['template'] ?? 'custom' }}"
                          data-matrix="{{ json_encode($k['matrix']) }}">
                    <i class="bi bi-sliders"></i> Permisos
                  </button>
                  <form method="POST" action="{{ $adminBase }}/mcp/keys/{{ $k['id'] }}/revoke" class="d-inline form-revoke" data-name="{{ $k['name'] }}">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Revocar"><i class="bi bi-x-octagon"></i></button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Actividad --}}
<div class="card mt-4">
  <div class="card-header"><h5 class="card-title mb-0">Actividad reciente</h5></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Fecha</th><th>Conexión</th><th>Acción</th><th>Vía</th><th>Resultado</th><th>IP</th></tr>
        </thead>
        <tbody>
          @if(empty($activity))
            <tr><td colspan="6" class="text-center text-muted py-3">Sin actividad todavía.</td></tr>
          @endif
          @foreach($activity as $a)
            <tr>
              <td><small>{{ date('d/m H:i:s', strtotime($a['created_at'])) }}</small></td>
              <td><small>{{ $a['key_name'] ?? '(revocada)' }}</small></td>
              <td><code style="font-size:0.78rem;">{{ $a['tool_name'] }}</code> <small class="text-muted">{{ $a['path'] }}</small></td>
              <td><span class="badge {{ ($a['source'] ?? 'rest') === 'mcp' ? 'bg-primary' : 'bg-secondary' }}">{{ strtoupper($a['source'] ?? 'rest') }}</span></td>
              <td>
                @if($a['success'])
                  <span class="text-success small"><i class="bi bi-check-circle"></i> {{ $a['status_code'] }}</span>
                @else
                  <span class="text-danger small"><i class="bi bi-x-circle"></i> {{ $a['status_code'] }}</span>
                @endif
                @if($a['duration_ms'] !== null)<small class="text-muted">· {{ $a['duration_ms'] }} ms</small>@endif
              </td>
              <td><small class="text-muted">{{ $a['ip_address'] }}</small></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Modal crear / editar --}}
@if($canEdit)
<div class="modal fade" id="connectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form method="POST" class="modal-content" id="connectionForm" action="{{ $adminBase }}/mcp/keys">
      {!! csrf_field() !!}
      <div class="modal-header">
        <h5 class="modal-title" id="connectionModalTitle">Nueva conexión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-7">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" id="fName" class="form-control" maxlength="100" placeholder="Ej: Claude Code portátil" required>
          </div>
          <div class="col-md-5" id="expiryGroup">
            <label class="form-label">Caduca en</label>
            <select name="expires" class="form-select">
              @foreach($expiryOptions as $val => $label)
                <option value="{{ $val }}" {{ $val === '90' ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <label class="form-label">Plantilla</label>
        <div class="row g-2 mb-3">
          @foreach($templates as $tKey => $tpl)
            <div class="col-6 col-md-3">
              <label class="mcp-template w-100" data-template="{{ $tKey }}">
                <input type="radio" name="template" value="{{ $tKey }}" {{ $tKey === 'writer' ? 'checked' : '' }}>
                <strong style="font-size:0.9rem;">{{ $tpl['label'] }}</strong>
                <small>{{ $tpl['hint'] }}</small>
              </label>
            </div>
          @endforeach
          <input type="radio" name="template" value="custom" id="tplCustom" class="d-none">
        </div>

        <label class="form-label">Permisos por sección <small class="text-muted" id="customHint" style="display:none;">(personalizado)</small></label>
        <div class="table-responsive">
          <table class="table table-bordered mcp-matrix mb-2">
            <thead class="table-light">
              <tr>
                <th>Sección</th>
                @foreach($levels as $lvl => $lvlLabel)<th><span class="mcp-lvl {{ $lvl }}">{{ $lvlLabel }}</span></th>@endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($sections as $sKey => $section)
                <tr>
                  <td><i class="bi {{ $section['icon'] }} me-1 text-muted"></i> {{ $section['label'] }}</td>
                  @foreach($levels as $lvl => $lvlLabel)
                    <td>
                      @if(in_array($lvl, $section['levels'], true))
                        <input type="checkbox" class="form-check-input perm-box" name="perm[{{ $sKey }}][]" value="{{ $lvl }}" data-section="{{ $sKey }}" data-level="{{ $lvl }}">
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="small text-muted mb-3">
          <span class="mcp-lvl write">Escribir</span> crea y edita borradores ·
          <span class="mcp-lvl publish">Publicar</span> pone contenido en vivo y permite editar lo publicado ·
          <span class="mcp-lvl delete">Borrar</span> mueve a la papelera (siempre con confirmación).
        </p>

        <details>
          <summary class="small text-muted mb-2" style="cursor:pointer;">Opciones avanzadas</summary>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small">IPs permitidas (opcional)</label>
              <input type="text" name="allowed_ips" id="fIps" class="form-control form-control-sm" placeholder="Ej: 83.45.10.2, 10.0.0.0/24">
              <div class="form-text">Vacío = desde cualquier IP. Útil para servidores o scripts con IP fija.</div>
            </div>
            <div class="col-md-4" id="rateGroup">
              <label class="form-label small">Peticiones / minuto</label>
              <input type="number" name="rate_limit" class="form-control form-control-sm" value="60" min="10" max="300">
            </div>
          </div>
        </details>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="connectionSubmit">Crear conexión</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
  // Copiar al portapapeles
  document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var el = document.getElementById(btn.dataset.copyTarget);
      if (!el) return;
      navigator.clipboard.writeText(el.innerText.trim()).then(function () {
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(function () { btn.innerHTML = original; }, 1500);
      });
    });
  });

  // Revocar con confirmación
  document.querySelectorAll('.form-revoke').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = 'El token de "' + form.dataset.name + '" dejará de funcionar inmediatamente. Esta acción no se puede deshacer.';
      if (window.Swal) {
        Swal.fire({ title: '¿Revocar conexión?', text: msg, icon: 'warning', showCancelButton: true, confirmButtonText: 'Revocar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
          .then(function (r) { if (r.isConfirmed) form.submit(); });
      } else if (confirm(msg)) {
        form.submit();
      }
    });
  });

  // Revocar todas: confirmación escribiendo REVOCAR
  var revokeAll = document.getElementById('formRevokeAll');
  if (revokeAll) {
    revokeAll.addEventListener('submit', function (e) {
      e.preventDefault();
      var send = function (value) {
        if (value !== 'REVOCAR') return;
        revokeAll.querySelector('input[name="confirm"]').value = value;
        revokeAll.submit();
      };
      var text = 'Todos los asistentes de IA y tokens perderán el acceso al sitio inmediatamente. Escribe REVOCAR para confirmar.';
      if (window.Swal) {
        Swal.fire({ title: '¿Revocar todas las conexiones?', text: text, icon: 'warning', input: 'text', inputPlaceholder: 'REVOCAR',
          showCancelButton: true, confirmButtonText: 'Revocar todas', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
          preConfirm: function (v) { if (v !== 'REVOCAR') { Swal.showValidationMessage('Escribe REVOCAR'); return false; } return v; }
        }).then(function (r) { if (r.isConfirmed) send(r.value); });
      } else {
        send(prompt(text));
      }
    });
  }

  var modalEl = document.getElementById('connectionModal');
  if (!modalEl) return;

  var templateMatrix = {!! json_encode($templateMatrix, $jsFlags) !!};
  var adminBase = {!! json_encode($adminBase, $jsFlags) !!};
  var form = document.getElementById('connectionForm');
  var boxes = form.querySelectorAll('.perm-box');
  var tplCards = form.querySelectorAll('.mcp-template');
  var customRadio = document.getElementById('tplCustom');
  var customHint = document.getElementById('customHint');

  function setMatrix(matrix) {
    boxes.forEach(function (b) {
      var lv = matrix[b.dataset.section] || [];
      b.checked = lv.indexOf(b.dataset.level) !== -1;
    });
  }

  function selectTemplate(name) {
    tplCards.forEach(function (c) {
      var on = c.dataset.template === name;
      c.classList.toggle('active', on);
      c.querySelector('input').checked = on;
    });
    customRadio.checked = name === 'custom';
    customHint.style.display = name === 'custom' ? 'inline' : 'none';
    if (templateMatrix[name]) setMatrix(templateMatrix[name]);
  }

  tplCards.forEach(function (c) {
    c.addEventListener('click', function () { selectTemplate(c.dataset.template); });
  });

  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      // Leer implica el resto de niveles
      var section = b.dataset.section;
      var sectionBoxes = form.querySelectorAll('.perm-box[data-section="' + section + '"]');
      if (b.checked && b.dataset.level !== 'read') {
        sectionBoxes.forEach(function (s) { if (s.dataset.level === 'read') s.checked = true; });
      }
      if (!b.checked && b.dataset.level === 'read') {
        sectionBoxes.forEach(function (s) { s.checked = false; });
      }
      selectTemplate('custom');
    });
  });

  var modal = new bootstrap.Modal(modalEl);

  var btnNew = document.getElementById('btnNewConnection');
  if (btnNew) {
    btnNew.addEventListener('click', function () {
      form.action = adminBase + '/mcp/keys';
      form.reset();
      document.getElementById('connectionModalTitle').textContent = 'Nueva conexión';
      document.getElementById('connectionSubmit').textContent = 'Crear conexión';
      document.getElementById('expiryGroup').style.display = '';
      document.getElementById('rateGroup').style.display = '';
      selectTemplate('writer');
      modal.show();
    });
  }

  document.querySelectorAll('.btn-edit-connection').forEach(function (btn) {
    btn.addEventListener('click', function () {
      form.action = adminBase + '/mcp/keys/' + btn.dataset.id;
      document.getElementById('connectionModalTitle').textContent = 'Permisos de "' + btn.dataset.name + '"';
      document.getElementById('connectionSubmit').textContent = 'Guardar permisos';
      document.getElementById('fName').value = btn.dataset.name;
      document.getElementById('fIps').value = btn.dataset.ips || '';
      document.getElementById('expiryGroup').style.display = 'none';
      document.getElementById('rateGroup').style.display = 'none';
      var tpl = btn.dataset.template || 'custom';
      selectTemplate(tpl);
      if (tpl === 'custom') setMatrix(JSON.parse(btn.dataset.matrix || '{}'));
      modal.show();
    });
  });
})();
</script>
@endpush
