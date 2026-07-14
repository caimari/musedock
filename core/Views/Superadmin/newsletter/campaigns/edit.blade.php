@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>{{ $title }}</h2>
      <div class="d-flex gap-2">
        <a href="/musedock/newsletter/campaigns" class="btn btn-outline-secondary">Volver</a>
        @if(in_array($campaign->status, ['draft','queued','paused']))
        <form method="POST" action="/musedock/newsletter/campaigns/{{ $campaign->id }}/queue">
          {!! csrf_field() !!}
          <button class="btn btn-success" type="submit">Poner en cola</button>
        </form>
        @endif
      </div>
    </div>

    @include('partials.alerts-sweetalert2')

    <div class="card">
      <div class="card-body">
        <form method="POST" action="/musedock/newsletter/campaigns/{{ $campaign->id }}/update">
          {!! csrf_field() !!}

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre interno</label>
              <input class="form-control" name="name" value="{{ $campaign->name }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tenant</label>
              @php
                $tenantName = 'Global';
                foreach($tenants as $tenant){ if((int)$tenant->id === (int)$campaign->tenant_id){ $tenantName = $tenant->name; break; } }
              @endphp
              <input class="form-control" value="{{ $tenantName }}" readonly>
            </div>
            <div class="col-md-8">
              <label class="form-label">Asunto</label>
              <input class="form-control" name="subject" value="{{ $campaign->subject }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Programar (opcional)</label>
              <input class="form-control" type="datetime-local" name="scheduled_at" value="{{ !empty($campaign->scheduled_at) ? date('Y-m-d\\TH:i', strtotime($campaign->scheduled_at)) : '' }}">
            </div>
            <div class="col-12">
              <label class="form-label">Preheader (opcional)</label>
              <input class="form-control" name="preheader" value="{{ $campaign->preheader }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">From name (opcional)</label>
              <input class="form-control" name="from_name" value="{{ $campaign->from_name }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">From email (opcional)</label>
              <input class="form-control" name="from_email" type="email" value="{{ $campaign->from_email }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Reply-to (opcional)</label>
              <input class="form-control" name="reply_to" type="email" value="{{ $campaign->reply_to }}">
            </div>
            <div class="col-12">
              <label class="form-label">Contenido HTML</label>
              <textarea class="form-control" name="html_content" rows="12">{{ $campaign->html_content }}</textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Texto plano (fallback)</label>
              <textarea class="form-control" name="text_content" rows="6">{{ $campaign->text_content }}</textarea>
            </div>
          </div>

          <div class="mt-4 d-flex justify-content-between align-items-center">
            <small class="text-muted">Estado: {{ strtoupper($campaign->status) }} | Total: {{ (int)$campaign->total_recipients }} | OK: {{ (int)$campaign->sent_count }} | Fail: {{ (int)$campaign->failed_count }}</small>
            <button class="btn btn-primary" type="submit">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
