@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>{{ $title }}</h2>
      <a href="/{{ admin_path() }}/newsletter/campaigns" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="/{{ admin_path() }}/newsletter/campaigns">
          {!! csrf_field() !!}

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nombre interno</label>
              <input class="form-control" name="name" placeholder="Novedades mayo">
            </div>
            <div class="col-md-4">
              <label class="form-label">Programar (opcional)</label>
              <input class="form-control" type="datetime-local" name="scheduled_at">
            </div>
            <div class="col-12">
              <label class="form-label">Asunto</label>
              <input class="form-control" name="subject" required>
            </div>
            <div class="col-12">
              <label class="form-label">Preheader (opcional)</label>
              <input class="form-control" name="preheader">
            </div>
            <div class="col-md-4">
              <label class="form-label">From name (opcional)</label>
              <input class="form-control" name="from_name">
            </div>
            <div class="col-md-4">
              <label class="form-label">From email (opcional)</label>
              <input class="form-control" name="from_email" type="email">
            </div>
            <div class="col-md-4">
              <label class="form-label">Reply-to (opcional)</label>
              <input class="form-control" name="reply_to" type="email">
            </div>
            <div class="col-12">
              <label class="form-label">Contenido HTML</label>
              <textarea class="form-control" name="html_content" rows="12" placeholder="<h1>Hola</h1>"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Texto plano (fallback)</label>
              <textarea class="form-control" name="text_content" rows="6"></textarea>
            </div>
          </div>

          <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Crear campaña</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
