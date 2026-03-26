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

      {{-- ─── Banner de Cookies ─── --}}
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Banner de Cookies</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="mb-3 form-check form-switch">
                <input type="checkbox" class="form-check-input" id="cookies_enabled" name="cookies_enabled" {{ ($settings['cookies_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="cookies_enabled">Activar banner de cookies</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3 form-check form-switch">
                <input type="checkbox" class="form-check-input" id="cookies_show_icon" name="cookies_show_icon" {{ ($settings['cookies_show_icon'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="cookies_show_icon">Mostrar icono 🍪 en el footer</label>
              </div>
            </div>
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

      {{-- ─── Diseño del Banner ─── --}}
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-layout-text-window me-2"></i>Diseño del Banner</h5>
        </div>
        <div class="card-body">
          @php $bannerLayout = $settings['cookies_banner_layout'] ?? 'card'; @endphp

          <label class="form-label fw-semibold mb-3">Elige el estilo del banner de cookies</label>

          <div class="row g-3 mb-4">
            {{-- Card (esquina inferior derecha) --}}
            <div class="col-md-4">
              <label class="layout-option d-block h-100 {{ $bannerLayout === 'card' ? 'active' : '' }}" data-layout="card">
                <input type="radio" name="cookies_banner_layout" value="card" class="d-none" {{ $bannerLayout === 'card' ? 'checked' : '' }}>
                <div class="layout-preview card-preview">
                  <div class="preview-page">
                    <div class="preview-header"></div>
                    <div class="preview-content">
                      <div class="preview-line"></div>
                      <div class="preview-line short"></div>
                    </div>
                    <div class="preview-banner-card"></div>
                  </div>
                </div>
                <div class="layout-label">
                  <strong>Card</strong>
                  <small class="d-block text-muted">Tarjeta flotante en esquina inferior derecha</small>
                </div>
              </label>
            </div>

            {{-- Bar (barra inferior de ancho completo) --}}
            <div class="col-md-4">
              <label class="layout-option d-block h-100 {{ $bannerLayout === 'bar' ? 'active' : '' }}" data-layout="bar">
                <input type="radio" name="cookies_banner_layout" value="bar" class="d-none" {{ $bannerLayout === 'bar' ? 'checked' : '' }}>
                <div class="layout-preview bar-preview">
                  <div class="preview-page">
                    <div class="preview-header"></div>
                    <div class="preview-content">
                      <div class="preview-line"></div>
                      <div class="preview-line short"></div>
                    </div>
                    <div class="preview-banner-bar"></div>
                  </div>
                </div>
                <div class="layout-label">
                  <strong>Barra</strong>
                  <small class="d-block text-muted">Barra estrecha fija en la parte inferior</small>
                </div>
              </label>
            </div>

            {{-- Modal (centrado) --}}
            <div class="col-md-4">
              <label class="layout-option d-block h-100 {{ $bannerLayout === 'modal' ? 'active' : '' }}" data-layout="modal">
                <input type="radio" name="cookies_banner_layout" value="modal" class="d-none" {{ $bannerLayout === 'modal' ? 'checked' : '' }}>
                <div class="layout-preview modal-preview">
                  <div class="preview-page">
                    <div class="preview-header"></div>
                    <div class="preview-content">
                      <div class="preview-line"></div>
                      <div class="preview-line short"></div>
                    </div>
                    <div class="preview-overlay">
                      <div class="preview-banner-modal"></div>
                    </div>
                  </div>
                </div>
                <div class="layout-label">
                  <strong>Modal</strong>
                  <small class="d-block text-muted">Ventana centrada con fondo oscuro</small>
                </div>
              </label>
            </div>
          </div>

          {{-- Colores --}}
          <h6 class="text-muted mb-3"><i class="bi bi-palette me-2"></i>Colores del banner</h6>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Fondo</label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="cookies_bg_color" name="cookies_bg_color" value="{{ $settings['cookies_bg_color'] ?? '#ffffff' }}">
                <input type="text" class="form-control" value="{{ $settings['cookies_bg_color'] ?? '#ffffff' }}" data-sync="cookies_bg_color" maxlength="7">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Texto</label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="cookies_text_color" name="cookies_text_color" value="{{ $settings['cookies_text_color'] ?? '#333333' }}">
                <input type="text" class="form-control" value="{{ $settings['cookies_text_color'] ?? '#333333' }}" data-sync="cookies_text_color" maxlength="7">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Botón Aceptar</label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="cookies_btn_accept_bg" name="cookies_btn_accept_bg" value="{{ $settings['cookies_btn_accept_bg'] ?? '#4CAF50' }}">
                <input type="text" class="form-control" value="{{ $settings['cookies_btn_accept_bg'] ?? '#4CAF50' }}" data-sync="cookies_btn_accept_bg" maxlength="7">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Botón Rechazar</label>
              <div class="input-group">
                <input type="color" class="form-control form-control-color" id="cookies_btn_reject_bg" name="cookies_btn_reject_bg" value="{{ $settings['cookies_btn_reject_bg'] ?? '#f44336' }}">
                <input type="text" class="form-control" value="{{ $settings['cookies_btn_reject_bg'] ?? '#f44336' }}" data-sync="cookies_btn_reject_bg" maxlength="7">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ─── Info --}}
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

@push('styles')
<style>
/* Layout selector */
.layout-option {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    cursor: pointer;
    transition: all .2s;
    background: #fff;
}
.layout-option:hover { border-color: #93c5fd; }
.layout-option.active { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

.layout-preview {
    background: #f8f9fa;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
}
.preview-page {
    position: relative;
    height: 120px;
    background: #f0f0f0;
}
.preview-header {
    height: 14px;
    background: #d1d5db;
}
.preview-content { padding: 8px 10px; }
.preview-line { height: 6px; background: #d1d5db; border-radius: 3px; margin-bottom: 5px; }
.preview-line.short { width: 60%; }

/* Card preview */
.preview-banner-card {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 45%;
    height: 36px;
    background: #3b82f6;
    border-radius: 4px;
    opacity: .85;
}

/* Bar preview */
.preview-banner-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: #3b82f6;
    opacity: .85;
}

/* Modal preview */
.preview-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.35);
    display: flex;
    align-items: center;
    justify-content: center;
}
.preview-banner-modal {
    width: 55%;
    height: 42px;
    background: #3b82f6;
    border-radius: 4px;
}

.layout-label {
    text-align: center;
    font-size: .85rem;
}

/* Color input sync */
.form-control-color {
    width: 42px;
    min-width: 42px;
    padding: 4px;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Page selects
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

  // Layout selector
  document.querySelectorAll('.layout-option').forEach(function(option) {
    option.addEventListener('click', function() {
      document.querySelectorAll('.layout-option').forEach(o => o.classList.remove('active'));
      this.classList.add('active');
      this.querySelector('input[type="radio"]').checked = true;
    });
  });

  // Color picker sync
  document.querySelectorAll('input[data-sync]').forEach(function(textInput) {
    const colorInput = document.getElementById(textInput.dataset.sync);
    if (!colorInput) return;

    colorInput.addEventListener('input', function() {
      textInput.value = this.value;
    });
    textInput.addEventListener('input', function() {
      if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        colorInput.value = this.value;
      }
    });
  });
});
</script>
@endpush
