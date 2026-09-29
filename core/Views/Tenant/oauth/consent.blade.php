@extends('layouts.login-tabler')

@section('title', $title)

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.oauth-app { width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;flex-shrink:0; }
.oauth-lvl { display:inline-block;font-size:0.7rem;padding:0.1rem 0.45rem;border-radius:4px;font-weight:600; }
.oauth-lvl.read { background:#e7f1ff;color:#0d6efd; }
.oauth-lvl.write { background:#fff3cd;color:#997404; }
.oauth-lvl.publish { background:#d1e7dd;color:#146c43; }
.oauth-lvl.delete { background:#f8d7da;color:#b02a37; }
.oauth-tpl { border:1px solid #e6e7e9;border-radius:8px;padding:0.55rem 0.7rem;cursor:pointer;height:100%;display:block; }
.oauth-tpl.active { border-color:#7c3aed;background:rgba(124,58,237,0.05); }
.oauth-tpl small { display:block;color:#6c757d;font-size:0.74rem;margin-top:0.15rem; }
.oauth-matrix th, .oauth-matrix td { text-align:center;vertical-align:middle;padding:0.4rem; }
.oauth-matrix th:first-child, .oauth-matrix td:first-child { text-align:left; }
</style>

<div class="page py-4">
  <div class="container py-2" style="max-width:640px;">
    <div class="card card-md">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="oauth-app"><i class="bi bi-robot"></i></div>
          <div>
            <h2 class="h2 mb-1">{{ $clientName }} quiere conectarse</h2>
            <div class="text-muted">a <strong>{{ $siteName }}</strong> ({{ $siteDomain }}) como <strong>{{ $userName }}</strong></div>
          </div>
        </div>

        @if($knownClient)
          <div class="alert alert-success py-2 mb-3">
            <i class="bi bi-patch-check-fill me-1"></i> El acceso se enviará a <strong>{{ $redirectHost }}</strong> ({{ $knownClient }}).
          </div>
        @elseif($isLoopback)
          <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-laptop me-1"></i> El acceso se enviará a una aplicación de <strong>este ordenador</strong> ({{ $redirectHost }}), por ejemplo Claude Code o Codex. Autoriza solo si acabas de iniciar la conexión tú.
          </div>
        @else
          <div class="alert alert-warning py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> El acceso se enviará a <strong>{{ $redirectHost }}</strong>. Autoriza solo si reconoces esta aplicación y acabas de iniciar la conexión.
          </div>
        @endif

        @if($existing)
          <p class="small text-muted mb-3"><i class="bi bi-arrow-repeat"></i> Ya habías conectado esta aplicación: al autorizar se actualizan sus permisos.</p>
        @endif

        <form method="POST" action="/oauth/authorize" id="consentForm">
          {!! csrf_field() !!}
          <input type="hidden" name="rid" value="{{ $rid }}">

          <label class="form-label">¿Qué podrá hacer?</label>
          <div class="row g-2 mb-3">
            @foreach($templates as $tKey => $tpl)
              <div class="col-6">
                <label class="oauth-tpl {{ $tKey === $selected ? 'active' : '' }}" data-template="{{ $tKey }}">
                  <input type="radio" name="template" value="{{ $tKey }}" class="form-check-input me-1" {{ $tKey === $selected ? 'checked' : '' }}>
                  <strong>{{ $tpl['label'] }}</strong>
                  <small>{{ $tpl['hint'] }}</small>
                </label>
              </div>
            @endforeach
            <input type="radio" name="template" value="custom" id="tplCustom" class="d-none">
          </div>

          <details class="mb-3" id="matrixDetails">
            <summary class="small text-muted" style="cursor:pointer;">Ajustar por sección</summary>
            <div class="table-responsive mt-2">
              <table class="table table-bordered oauth-matrix mb-1">
                <thead>
                  <tr>
                    <th>Sección</th>
                    @foreach($levels as $lvl => $lvlLabel)<th><span class="oauth-lvl {{ $lvl }}">{{ $lvlLabel }}</span></th>@endforeach
                  </tr>
                </thead>
                <tbody>
                  @foreach($sections as $sKey => $section)
                    <tr>
                      <td>{{ $section['label'] }}</td>
                      @foreach($levels as $lvl => $lvlLabel)
                        <td>
                          @if(in_array($lvl, $section['levels'], true) && in_array($lvl, $grantable[$sKey] ?? [], true))
                            <input type="checkbox" class="form-check-input perm-box" name="perm[{{ $sKey }}][]" value="{{ $lvl }}" data-section="{{ $sKey }}" data-level="{{ $lvl }}">
                          @elseif(in_array($lvl, $section['levels'], true))
                            <i class="bi bi-lock text-muted" title="Tu usuario no tiene este permiso en el panel"></i>
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
          </details>

          @php
            $limited = false;
            foreach ($sections as $sKey => $section) {
              if (array_diff($section['levels'], $grantable[$sKey] ?? [])) { $limited = true; break; }
            }
          @endphp
          @if($limited)
            <p class="small text-muted mb-2"><i class="bi bi-lock"></i> Algunas opciones están bloqueadas porque tu usuario no tiene esos permisos en el panel. La conexión nunca podrá hacer más que tú.</p>
          @endif

          <ul class="small text-muted ps-3 mb-4">
            <li>Sin <em>Publicar</em>, todo lo que cree queda en borrador para que lo revises.</li>
            <li>Borrar siempre le pide confirmación y manda a la papelera.</li>
            <li>Puedes cambiar los permisos o revocar el acceso en <strong>Ajustes → Conexiones IA (MCP)</strong>.</li>
          </ul>

          <div class="d-flex gap-2">
            <button type="submit" name="decision" value="deny" class="btn btn-outline-secondary flex-fill">Cancelar</button>
            <button type="submit" name="decision" value="approve" class="btn btn-primary flex-fill"><i class="bi bi-check2 me-1"></i> Autorizar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var templateMatrix = {!! json_encode($templateMatrix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
  var form = document.getElementById('consentForm');
  var boxes = form.querySelectorAll('.perm-box');
  var cards = form.querySelectorAll('.oauth-tpl');
  var custom = document.getElementById('tplCustom');

  function setMatrix(matrix) {
    boxes.forEach(function (b) {
      b.checked = (matrix[b.dataset.section] || []).indexOf(b.dataset.level) !== -1;
    });
  }
  function select(name) {
    cards.forEach(function (c) {
      var on = c.dataset.template === name;
      c.classList.toggle('active', on);
      c.querySelector('input').checked = on;
    });
    custom.checked = name === 'custom';
    if (templateMatrix[name]) setMatrix(templateMatrix[name]);
  }
  cards.forEach(function (c) { c.addEventListener('click', function () { select(c.dataset.template); }); });
  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      var same = form.querySelectorAll('.perm-box[data-section="' + b.dataset.section + '"]');
      if (b.checked && b.dataset.level !== 'read') same.forEach(function (s) { if (s.dataset.level === 'read') s.checked = true; });
      if (!b.checked && b.dataset.level === 'read') same.forEach(function (s) { s.checked = false; });
      select('custom');
    });
  });
  select({!! json_encode($selected) !!});
})();
</script>
@endpush
