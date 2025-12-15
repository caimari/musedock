@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Configuración de Cookies</h3>
  </div>
  <div class="card-body">
    @include('partials.alerts-sweetalert2')

    <form method="POST" action="/{{ admin_path() }}/settings/cookies">
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

          <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Textos multiidioma:</strong> Los textos del banner de cookies se traducen automáticamente según el idioma del visitante.
            Las traducciones se encuentran en los archivos <code>lang/tenant/es.json</code> y <code>lang/tenant/en.json</code> en la sección <code>"cookies"</code>.
          </div>

          @php
            $currentPolicyUrl = $settings['cookies_policy_url'] ?? '/p/cookie-policy';
            $currentTermsUrl = $settings['cookies_terms_url'] ?? '/p/terms-and-conditions';
            $availableUrls = array_map(fn($p) => '/' . ($p['prefix'] ?: 'p') . '/' . $p['slug'], $availablePages ?? []);
          @endphp

          <h6 class="text-muted mb-3"><i class="fas fa-link me-2"></i>Enlaces legales en el banner</h6>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Página de Política de Cookies</label>
              <select name="cookies_policy_url" class="form-control page-select" data-target="cookies_policy_url_custom">
                <option value="">-- Seleccionar página --</option>
                @foreach(($availablePages ?? []) as $pageItem)
                  @php $pageUrl = '/' . ($pageItem['prefix'] ?: 'p') . '/' . $pageItem['slug']; @endphp
                  <option value="{{ $pageUrl }}" {{ $currentPolicyUrl === $pageUrl ? 'selected' : '' }}>
                    {{ $pageItem['title'] }} ({{ $pageUrl }})
                  </option>
                @endforeach
                <option value="custom" {{ !empty($currentPolicyUrl) && !in_array($currentPolicyUrl, $availableUrls, true) ? 'selected' : '' }}>
                  URL personalizada...
                </option>
              </select>
              <input type="text" name="cookies_policy_url_custom" id="cookies_policy_url_custom" class="form-control mt-2"
                     value="{{ $currentPolicyUrl }}"
                     placeholder="/p/cookie-policy"
                     style="{{ !empty($currentPolicyUrl) && !in_array($currentPolicyUrl, $availableUrls, true) ? '' : 'display:none;' }}">
              <small class="text-muted">URL de la página de política de cookies</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Página de Términos y Condiciones</label>
              <select name="cookies_terms_url" class="form-control page-select" data-target="cookies_terms_url_custom">
                <option value="">-- No mostrar enlace --</option>
                @foreach(($availablePages ?? []) as $pageItem)
                  @php $pageUrl = '/' . ($pageItem['prefix'] ?: 'p') . '/' . $pageItem['slug']; @endphp
                  <option value="{{ $pageUrl }}" {{ $currentTermsUrl === $pageUrl ? 'selected' : '' }}>
                    {{ $pageItem['title'] }} ({{ $pageUrl }})
                  </option>
                @endforeach
                <option value="custom" {{ !empty($currentTermsUrl) && !in_array($currentTermsUrl, $availableUrls, true) ? 'selected' : '' }}>
                  URL personalizada...
                </option>
              </select>
              <input type="text" name="cookies_terms_url_custom" id="cookies_terms_url_custom" class="form-control mt-2"
                     value="{{ $currentTermsUrl }}"
                     placeholder="/p/terms-and-conditions"
                     style="{{ !empty($currentTermsUrl) && !in_array($currentTermsUrl, $availableUrls, true) ? '' : 'display:none;' }}">
              <small class="text-muted">URL de la página de términos y condiciones</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background-color: #e7f1ff; color: #0d6efd;">
          <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información sobre el sistema de cookies</h5>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-4">
            <h5>¿Cómo funciona el sistema de cookies?</h5>
            <p>El sistema implementado distingue entre dos tipos de cookies:</p>
            <ul>
              <li><strong>Cookies Básicas/Esenciales</strong>: Son necesarias para el funcionamiento básico del sitio. Incluyen cookies de sesión, autenticación y preferencias de usuario. Siempre están habilitadas.</li>
              <li><strong>Cookies No Esenciales</strong>: Incluyen cookies de análisis (como Google Analytics), cookies de publicidad y marketing, y cookies de terceros para redes sociales. Estas sólo se activan si el usuario acepta todas las cookies.</li>
            </ul>

            <p>Cuando un usuario visita el sitio por primera vez, verá el banner de cookies. Tiene tres opciones:</p>
            <ol>
              <li><strong>Gestionar preferencias:</strong> Abre el modal para ver categorías de cookies.</li>
              <li><strong>Rechazar todo:</strong> Solo cookies esenciales.</li>
              <li><strong>Aceptar todas:</strong> Permite toda la funcionalidad incluyendo análisis y marketing.</li>
            </ol>

            <p>La elección del usuario se guarda en el almacenamiento local del navegador, por lo que no verá el banner de nuevo a menos que borre sus datos de navegación.</p>
          </div>

          <div class="alert alert-warning">
            <h5>Importante: Páginas de políticas</h5>
            <p>Para cumplir con regulaciones como GDPR, es necesario crear páginas que expliquen detalladamente:</p>
            <ul>
              <li>Qué son las cookies</li>
              <li>Qué tipos de cookies utiliza tu sitio</li>
              <li>Cómo se utilizan y con qué propósito</li>
              <li>Cómo el usuario puede gestionar sus cookies</li>
            </ul>
            <p>Puedes crear estas páginas utilizando el editor de páginas del CMS. <strong>Las páginas soportan traducciones multiidioma</strong>, por lo que el contenido se mostrará en el idioma del visitante automáticamente.</p>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.page-select').forEach(function(select) {
    const targetId = select.dataset.target;
    const customInput = document.getElementById(targetId);

    if (customInput) {
      select.addEventListener('change', function() {
        if (this.value === 'custom') {
          customInput.style.display = 'block';
          customInput.focus();
        } else {
          customInput.style.display = 'none';
          customInput.value = this.value;
        }
      });
    }
  });

  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function() {
      document.querySelectorAll('.page-select').forEach(function(select) {
        const targetId = select.dataset.target;
        const customInput = document.getElementById(targetId);

        if (customInput) {
          if (select.value !== 'custom' && select.value !== '') {
            customInput.value = select.value;
          }
          const baseName = select.name;
          customInput.name = baseName;
          select.name = '';
        }
      });
    });
  }
});
</script>
@endpush

