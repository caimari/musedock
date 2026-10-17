@extends('layouts.app')
@section('title', $title)
@section('content')
@php
  $byId = [];
  foreach ($recipients as $r) { $byId[(int) $r['id']] = $r; }
  $activeRecipients = array_values(array_filter($recipients, fn ($r) => (int) $r['enabled'] === 1));
  $groups = [];
  foreach ($types as $key => $t) { $groups[$t['group'] ?? 'Otros'][$key] = $t; }
@endphp
<div class="row g-4">
  <div class="col-12 col-xl-10">

    <div class="card mb-4">
      <div class="card-header"><h3 class="card-title mb-0"><i class="bi bi-bell me-2"></i>Avisos</h3></div>
      <div class="card-body">
        <p class="text-muted mb-0" style="font-size:.9rem;">
          Un solo sitio para decidir quién recibe los avisos de la plataforma (bloqueos de Cloudflare, dominios a punto de caducar,
          caídas de SEO, ataques…) y en qué idioma. Cada módulo o plugin que envía avisos aparece aquí con los suyos.
          Los correos a clientes y usuarios (activaciones, contraseñas, pedidos) no pasan por aquí.
        </p>
      </div>
    </div>

    {{-- Destinatarios --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Destinatarios</h5></div>
      <div class="card-body">
        @if(!$recipients)
          <div class="alert alert-info py-2" style="font-size:.9rem;">
            No hay destinatarios propios: los avisos van a <strong>los superadmins</strong>, cada uno en el idioma que tenga en
            <a href="/musedock/profile">su perfil</a>:
            @foreach($admins as $a)
              <span class="badge bg-light text-dark border ms-1">{{ $a['email'] }} · {{ strtoupper($a['locale'] ?: 'es') }}</span>
            @endforeach
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
              <thead><tr><th>Correo</th><th>Nombre</th><th style="width:150px;">Idioma</th><th style="width:90px;">Activo</th><th style="width:150px;"></th></tr></thead>
              <tbody>
              @foreach($recipients as $r)
                <tr>
                  <form method="POST" action="/musedock/settings/notifications/recipient">{!! csrf_field() !!}
                    <input type="hidden" name="id" value="{{ $r['id'] }}">
                    <td>{{ $r['email'] }}</td>
                    <td class="text-muted">{{ $r['name'] ?: '—' }}</td>
                    <td><select name="locale" class="form-select form-select-sm">@foreach($locales as $lc => $ln)<option value="{{ $lc }}" @selected($r['locale'] === $lc)>{{ $ln }}</option>@endforeach</select></td>
                    <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked((int) $r['enabled'] === 1)></div></td>
                    <td class="text-end text-nowrap">
                      <button class="btn btn-outline-primary btn-sm" name="action" value="save" title="Guardar"><i class="bi bi-check2"></i></button>
                      <button class="btn btn-outline-danger btn-sm" name="action" value="delete" title="Quitar" onclick="return confirm('¿Quitar {{ $r['email'] }} de los avisos?')"><i class="bi bi-trash"></i></button>
                    </td>
                  </form>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <div class="form-text mb-3">Con destinatarios propios, los superadmins dejan de recibir avisos salvo que estén en la lista.</div>
        @endif

        <form method="POST" action="/musedock/settings/notifications/recipient/add" class="row g-2 align-items-end">{!! csrf_field() !!}
          <div class="col-md-4"><label class="form-label small mb-1">Correo</label><input type="email" name="email" class="form-control form-control-sm" required placeholder="avisos@tudominio.com"></div>
          <div class="col-md-3"><label class="form-label small mb-1">Nombre (opcional)</label><input type="text" name="name" class="form-control form-control-sm"></div>
          <div class="col-md-3"><label class="form-label small mb-1">Idioma</label><select name="locale" class="form-select form-select-sm">@foreach($locales as $lc => $ln)<option value="{{ $lc }}">{{ $ln }}</option>@endforeach</select></div>
          <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Añadir</button></div>
        </form>
      </div>
    </div>

    {{-- Tipos de aviso --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Tipos de aviso</h5></div>
      <div class="card-body">
        <form method="POST" action="/musedock/settings/notifications/types" id="nc-types">{!! csrf_field() !!}</form>
        @foreach($groups as $group => $items)
          <h6 class="text-uppercase text-muted small mt-2 mb-2">{{ $group }}</h6>
          @foreach($items as $key => $t)
            @php $some = $t['recipients'] !== null; @endphp
            <div class="border rounded p-3 mb-2">
              <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                  <div class="form-check form-switch">
                    <input form="nc-types" class="form-check-input" type="checkbox" id="nc-{{ $key }}" name="types[{{ $key }}][enabled]" value="1" @checked($t['enabled'])>
                    <label class="form-check-label fw-semibold" for="nc-{{ $key }}">{{ $t['es'] }}</label>
                    @if(!$t['enabled'])<span class="badge bg-secondary ms-1">Silenciado</span>@endif
                  </div>
                  <div class="text-muted" style="font-size:.85rem;">{{ $t['desc_es'] }}</div>
                </div>
                <form method="POST" action="/musedock/settings/notifications/test">{!! csrf_field() !!}
                  <input type="hidden" name="type" value="{{ $key }}">
                  <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-send me-1"></i>Enviar prueba</button>
                </form>
              </div>
              @if($activeRecipients)
                <div class="mt-2" style="font-size:.85rem;">
                  <div class="form-check form-check-inline">
                    <input form="nc-types" class="form-check-input" type="radio" name="types[{{ $key }}][scope]" id="nc-{{ $key }}-all" value="all" @checked(!$some)>
                    <label class="form-check-label" for="nc-{{ $key }}-all">A todos los destinatarios</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input form="nc-types" class="form-check-input" type="radio" name="types[{{ $key }}][scope]" id="nc-{{ $key }}-some" value="some" @checked($some)>
                    <label class="form-check-label" for="nc-{{ $key }}-some">Solo a:</label>
                  </div>
                  @foreach($activeRecipients as $r)
                    <div class="form-check form-check-inline">
                      <input form="nc-types" class="form-check-input" type="checkbox" name="types[{{ $key }}][recipients][]" id="nc-{{ $key }}-r{{ $r['id'] }}" value="{{ $r['id'] }}" @checked($some && in_array((int) $r['id'], $t['recipients'], true))>
                      <label class="form-check-label" for="nc-{{ $key }}-r{{ $r['id'] }}">{{ $r['email'] }}</label>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          @endforeach
        @endforeach
        <button form="nc-types" class="btn btn-primary btn-sm mt-2" type="submit"><i class="bi bi-check2 me-1"></i> Guardar tipos de aviso</button>
      </div>
    </div>

    {{-- Registro --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimos avisos enviados</h5></div>
      <div class="card-body">
        @if(!$log)
          <p class="text-muted mb-0">Aún no se ha enviado ningún aviso por aquí.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:.85rem;">
              <thead><tr><th>Cuándo (UTC)</th><th>Tipo</th><th>Para</th><th>Idioma</th><th>Asunto</th><th></th></tr></thead>
              <tbody>
              @foreach($log as $l)
                <tr>
                  <td class="text-nowrap">{{ $l['created_at'] }}</td>
                  <td>{{ $types[$l['type_key']]['es'] ?? $l['type_key'] }}</td>
                  <td>{{ $l['recipient'] }}</td>
                  <td>{{ strtoupper($l['locale']) }}</td>
                  <td>{{ $l['subject'] }}</td>
                  <td>@if((int) $l['ok'])<span class="badge bg-success">Enviado</span>@else<span class="badge bg-danger">Falló</span>@endif</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <div class="form-text">Se guardan 90 días.</div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
