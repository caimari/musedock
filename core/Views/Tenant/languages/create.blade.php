@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Añadir nuevo idioma</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="/{{ admin_path() }}/languages/store">
          {!! csrf_field() !!}
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Código del idioma <span class="text-muted">(ej. "es", "en", "fr")</span></label>
              <input type="text" name="code" class="form-control" required pattern="[a-z]{2,5}" title="2-5 letras minúsculas">
              <small class="text-muted">Formato ISO 639-1 (2 letras) o ISO 639-2 (3 letras). Ejemplos: es, en, fr, pt, de</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Nombre del idioma</label>
              <input type="text" name="name" class="form-control" required placeholder="Ej: Español, English, Français">
              <small class="text-muted">Nombre que se mostrará en los selectores</small>
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck" checked>
            <label class="form-check-label" for="activeCheck">Idioma activo</label>
            <br>
            <small class="text-muted">Los idiomas inactivos no aparecen en los selectores del sitio</small>
          </div>

          <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Idiomas comunes:</strong>
            <ul class="mb-0 mt-2">
              <li><code>es</code> - Español</li>
              <li><code>en</code> - English</li>
              <li><code>fr</code> - Français</li>
              <li><code>de</code> - Deutsch</li>
              <li><code>pt</code> - Português</li>
              <li><code>it</code> - Italiano</li>
              <li><code>ca</code> - Català</li>
            </ul>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-save me-1"></i> Guardar idioma
            </button>
            <a href="/{{ admin_path() }}/languages" class="btn btn-secondary">
              <i class="bi bi-x-lg me-1"></i> Cancelar
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
