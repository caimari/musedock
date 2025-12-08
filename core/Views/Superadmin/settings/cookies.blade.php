@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Configuración de Cookies</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.cookies.update') }}">
      {!! csrf_field() !!}
      
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Banner de Cookies</h5>
        </div>
        <div class="card-body">
          <div class="mb-3 form-check form-switch">
            <input type="checkbox" class="form-check-input" id="cookies_enabled" name="cookies_enabled" {{ ($settings['cookies_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="cookies_enabled">Activar banner de cookies</label>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Texto del banner</label>
            <textarea name="cookies_text" class="form-control" rows="3">{{ $settings['cookies_text'] ?? 'Este sitio utiliza cookies para mejorar tu experiencia. Puedes elegir qué cookies aceptar.' }}</textarea>
            <small class="text-muted">Mensaje que se mostrará en el banner de cookies</small>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Botón "Aceptar básicas"</label>
              <input type="text" name="cookies_accept_basic" class="form-control" value="{{ $settings['cookies_accept_basic'] ?? 'Aceptar básicas' }}">
              <small class="text-muted">Texto para el botón de cookies esenciales</small>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Botón "Aceptar todas"</label>
              <input type="text" name="cookies_accept_all" class="form-control" value="{{ $settings['cookies_accept_all'] ?? 'Aceptar todas' }}">
              <small class="text-muted">Texto para el botón de todas las cookies</small>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Enlace "Más información"</label>
              <input type="text" name="cookies_more_info" class="form-control" value="{{ $settings['cookies_more_info'] ?? 'Más información' }}">
              <small class="text-muted">Texto para el enlace a la política de cookies</small>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">URL de política de cookies</label>
              <input type="text" name="cookies_policy_url" class="form-control" value="{{ $settings['cookies_policy_url'] ?? '/politica-cookies' }}">
              <small class="text-muted">Ruta a la página de política de cookies</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="card mb-4">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0">Información sobre el sistema de cookies</h5>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-4">
            <h5>¿Cómo funciona el sistema de cookies?</h5>
            <p>El sistema implementado distingue entre dos tipos de cookies:</p>
            <ul>
              <li><strong>Cookies Básicas/Esenciales</strong>: Son necesarias para el funcionamiento básico del sitio. Incluyen cookies de sesión, autenticación y preferencias de usuario. Siempre están habilitadas, incluso si el usuario selecciona sólo las básicas.</li>
              <li><strong>Cookies No Esenciales</strong>: Incluyen cookies de análisis (como Google Analytics), cookies de publicidad y marketing, y cookies de terceros para redes sociales. Estas sólo se activan si el usuario acepta todas las cookies.</li>
            </ul>
            
            <p>Cuando un usuario visita el sitio por primera vez, verá el banner de cookies. Tiene tres opciones:</p>
            <ol>
              <li>Aceptar sólo cookies básicas: mantiene la funcionalidad esencial pero no permite el seguimiento.</li>
              <li>Aceptar todas las cookies: permite toda la funcionalidad incluyendo análisis y marketing.</li>
              <li>Ver más información: lleva a la página de política de cookies.</li>
            </ol>
            
            <p>La elección del usuario se guarda en el almacenamiento local del navegador, por lo que no verá el banner de nuevo a menos que borre sus datos de navegación.</p>
          </div>
          
          <div class="alert alert-warning">
            <h5>Importante: Páginas de políticas</h5>
            <p>Para cumplir con las regulaciones como GDPR, es necesario crear una página en <code>/politica-cookies</code> que explique detalladamente:</p>
            <ul>
              <li>Qué son las cookies</li>
              <li>Qué tipos de cookies utiliza tu sitio</li>
              <li>Cómo se utilizan y con qué propósito</li>
              <li>Cómo el usuario puede gestionar sus cookies</li>
            </ul>
            <p>Puedes crear esta página utilizando el editor de páginas del CMS.</p>
          </div>
        </div>
      </div>
      
      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-2"></i>Guardar configuración
        </button>
      </div>
    </form>
  </div>
</div>
@endsection