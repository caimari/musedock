@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2><i class="bi bi-megaphone me-2"></i>{{ $title }}</h2>
        <p class="text-muted mb-0">Campañas del tenant actual.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="/{{ admin_path() }}/newsletter" class="btn btn-outline-secondary">Suscriptores</a>
        <a href="/{{ admin_path() }}/newsletter/campaigns/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva campaña</a>
      </div>
    </div>

    @include('partials.alerts-sweetalert2')

    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" action="/{{ admin_path() }}/newsletter/campaigns" class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Buscar</label>
            <input class="form-control" type="text" name="q" value="{{ $q }}" placeholder="nombre o asunto">
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select class="form-select" name="status">
              <option value="" {{ $status === '' ? 'selected' : '' }}>Todos</option>
              @foreach(['draft' => 'Borrador', 'queued' => 'En cola', 'sending' => 'Enviando', 'sent' => 'Enviada', 'paused' => 'Pausada', 'cancelled' => 'Cancelada'] as $k => $v)
                <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $v }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Filtrar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Campaña</th>
              <th>Estado</th>
              <th>Envíos</th>
              <th>Aperturas</th>
              <th>Clics</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($campaigns as $c)
            <tr>
              <td>
                <strong>{{ $c->name }}</strong><br>
                <small class="text-muted">{{ $c->subject }}</small>
              </td>
              <td><span class="badge bg-secondary text-uppercase">{{ $c->status }}</span></td>
              <td>
                <small>Total: {{ (int)$c->total_recipients }}</small><br>
                <small class="text-success">OK: {{ (int)$c->sent_count }}</small> /
                <small class="text-danger">Fail: {{ (int)$c->failed_count }}</small>
              </td>
              <td>{{ (int)$c->open_count }}</td>
              <td>{{ (int)$c->click_count }}</td>
              <td>
                <div class="d-flex gap-2">
                  <a href="/{{ admin_path() }}/newsletter/campaigns/{{ $c->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                  @if(in_array($c->status, ['draft','queued','paused']))
                  <form method="POST" action="/{{ admin_path() }}/newsletter/campaigns/{{ $c->id }}/queue" class="d-inline">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-sm btn-success">Poner en cola</button>
                  </form>
                  @endif
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No hay campañas todavía.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
