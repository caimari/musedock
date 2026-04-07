@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
/* Placeholders más claros para distinguir del texto escrito */
.form-control::placeholder,
.form-select::placeholder,
input::placeholder,
textarea::placeholder {
    color: #adb5bd !important;
    opacity: 0.7 !important;
    font-style: italic;
}
</style>
@endpush

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Ajustes generales</h3>
  </div>

  <div class="card-body">
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
      {!! csrf_field() !!}
      
      <!-- Información básica del sitio -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Información del sitio</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Título del sitio <span class="text-danger">*</span></label>
            <input type="text" name="site_name" class="form-control" value="{{ $settings['site_name'] ?? '' }}" required>
            <small class="text-muted">Nombre principal de tu sitio web</small>
          </div>

          <div class="row">
            <div class="col-md-8 mb-3">
              <label class="form-label">Subtítulo del sitio</label>
              <input type="text" name="site_subtitle" class="form-control" value="{{ $settings['site_subtitle'] ?? '' }}" placeholder="Lema o slogan del sitio">
            </div>
            <div class="col-md-4 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="show_subtitle" name="show_subtitle" {{ ($settings['show_subtitle'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="show_subtitle">Mostrar subtítulo</label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Descripción SEO</label>
            <textarea name="site_description" class="form-control" rows="2">{{ $settings['site_description'] ?? '' }}</textarea>
            <small class="text-muted">Breve descripción para SEO y compartir en redes sociales</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Correo del administrador</label>
            <input type="email" name="admin_email" class="form-control" value="{{ $settings['admin_email'] ?? '' }}">
            <small class="text-muted">Correo para notificaciones y correspondencia</small>
          </div>
        </div>
      </div>

      <!-- Logotipo e identidad visual -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Logotipo e identidad visual</h5>
        </div>

        <div class="card-body">
          <!-- Logo -->
          <div class="mb-3">
            <label class="form-label">Logotipo del sitio</label>
            <input type="file" name="site_logo" class="form-control" accept="image/*">

            <div class="mt-3">
                @php
                    $logoSetting = $settings['site_logo'] ?? '';
                    $logoPreview = public_file_url($logoSetting, 'themes/default/img/logo/logo.png');
                @endphp
                <img src="{{ $logoPreview }}" 
                     alt="Logo actual" 
                     style="max-height: 80px; max-width: 100%; border: 1px solid #ddd; padding: 5px;"
                     onerror="this.onerror=null; this.src='{{ asset('themes/default/img/logo/logo.png') }}';">

                @if(!empty($logoSetting))
                  <p class="text-muted mt-2">Logo actual: <code>{{ $logoSetting }}</code></p>

                  <!-- Botón eliminar logo -->
                  <button type="button" class="btn btn-danger btn-sm mt-2" id="delete-logo-btn">
                    <i class="fas fa-trash-alt me-1"></i>Eliminar logo actual
                  </button>
                @endif
            </div>
          </div>

          <!-- Favicon -->
          <div class="mb-3">
            <label class="form-label">Favicon del sitio</label>
            <input type="file" name="site_favicon" class="form-control" accept="image/x-icon, image/png">

            <div class="mt-3">
                @php
                    $faviconSetting = $settings['site_favicon'] ?? '';
                    $faviconPreview = public_file_url($faviconSetting, 'img/favicon.png');
                @endphp
                <img src="{{ $faviconPreview }}"
                     alt="Favicon actual"
                     style="max-height: 40px; max-width: 40px; border: 1px solid #ddd; padding: 5px;"
                     onerror="this.onerror=null; this.src='{{ asset('img/favicon.png') }}';">

                @if(!empty($faviconSetting))
                  <p class="text-muted mt-2">Favicon actual: <code>{{ $faviconSetting }}</code></p>
                @endif
            </div>
          </div>

			@if(!empty($faviconSetting))
			  <button type="button" class="btn btn-danger btn-sm mt-2" id="delete-favicon-btn">
				<i class="fas fa-trash-alt me-1"></i>Eliminar favicon actual
			  </button>
			@endif

          <!-- Opciones visuales -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="show_logo" name="show_logo" {{ ($settings['show_logo'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="show_logo">Mostrar logotipo</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="show_title" name="show_title" {{ ($settings['show_title'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="show_title">Mostrar título del sitio</label>
              </div>
            </div>
          </div>

          <small class="text-muted">Puedes elegir mostrar ambos o solo uno de ellos</small>
        </div>
      </div>

      <!-- Contacto y Footer -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Información de contacto y footer</h5>
        </div>

        <div class="card-body">
          @php
              // Obtener idiomas activos para campos traducibles
              $activeLanguages = \Screenart\Musedock\Database::table('languages')
                  ->where('active', 1)
                  ->whereNull('tenant_id')
                  ->orderBy('order_position')
                  ->get();
          @endphp

          <div class="mb-3">
            <label class="form-label">Descripción corta del footer</label>
            <small class="text-muted d-block mb-2">Texto que se mostrará en la primera columna del footer (traducible por idioma)</small>

            @if(count($activeLanguages) > 1)
              {{-- Pestañas de idiomas --}}
              <ul class="nav nav-tabs" id="footerDescTabs" role="tablist">
                @foreach($activeLanguages as $index => $lang)
                  <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                            id="footer-desc-{{ $lang->code }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#footer-desc-{{ $lang->code }}"
                            type="button"
                            role="tab">
                      {{ strtoupper($lang->code) }} - {{ $lang->name }}
                    </button>
                  </li>
                @endforeach
              </ul>

              <div class="tab-content border border-top-0 p-3 rounded-bottom" id="footerDescTabsContent">
                @foreach($activeLanguages as $index => $lang)
                  <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                       id="footer-desc-{{ $lang->code }}"
                       role="tabpanel">
                    <textarea name="footer_short_description_{{ $lang->code }}"
                              class="form-control"
                              rows="3"
                              placeholder="Descripción en {{ $lang->name }}">{{ $settings['footer_short_description_' . $lang->code] ?? ($index === 0 ? ($settings['footer_short_description'] ?? '') : '') }}</textarea>
                  </div>
                @endforeach
              </div>
            @else
              {{-- Solo un idioma, mostrar textarea simple --}}
              <textarea name="footer_short_description" class="form-control" rows="3" placeholder="Breve descripción que aparecerá en el pie de página">{{ $settings['footer_short_description'] ?? '' }}</textarea>
            @endif
          </div>

          <div class="mb-3">
            <label class="form-label">Dirección de contacto</label>
            <input type="text" name="contact_address" class="form-control" value="{{ $settings['contact_address'] ?? '' }}" placeholder="Calle Principal 123, Ciudad">
            <small class="text-muted">Dirección física que se mostrará en el footer</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Correo de contacto</label>
            <input type="email" name="contact_email" class="form-control" value="{{ $settings['contact_email'] ?? '' }}" placeholder="contacto@ejemplo.com">
            <small class="text-muted">Email público para contacto</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Teléfono de contacto</label>
            <input type="text" name="contact_phone" class="form-control" value="{{ $settings['contact_phone'] ?? '' }}" placeholder="+34 123 456 789">
            <small class="text-muted">Teléfono que se mostrará en el footer</small>
          </div>

          <div class="mb-3">
            <label class="form-label">WhatsApp</label>
            <input type="text" name="contact_whatsapp" class="form-control" value="{{ $settings['contact_whatsapp'] ?? '' }}" placeholder="+34 123 456 789">
            <small class="text-muted">Número de WhatsApp (solo números con prefijo internacional)</small>
          </div>

          <hr class="my-4">

          <div class="mb-3">
            <label class="form-label">Texto de copyright</label>
            <input type="text" name="footer_copyright" class="form-control" value="{{ $settings['footer_copyright'] ?? '' }}" placeholder='© Copyright <a href="https://tudominio.com">Tu Empresa</a> {{ date("Y") }}.'>
            <small class="text-muted">Ejemplo: <code>&copy; Copyright &lt;a href="https://tudominio.com"&gt;Tu Empresa&lt;/a&gt; {{ date('Y') }}.</code></small>
          </div>
        </div>
      </div>

      <!-- Zona horaria y formatos -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Zona horaria y formatos</h5>
        </div>

        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Zona horaria</label>
            <select name="timezone" class="form-select">
              @foreach($timezones as $value => $label)
                <option value="{{ $value }}" {{ ($settings['timezone'] ?? 'UTC') == $value ? 'selected' : '' }}>
                  {{ $label }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Formato de fecha</label>
            <select name="date_format" class="form-select">
              @foreach($dateFormats as $format => $example)
                <option value="{{ $format }}" {{ ($settings['date_format'] ?? 'd/m/Y') == $format ? 'selected' : '' }}>
                  {{ $example }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Formato de hora</label>
            <select name="time_format" class="form-select">
              @foreach($timeFormats as $format => $example)
                <option value="{{ $format }}" {{ ($settings['time_format'] ?? 'H:i') == $format ? 'selected' : '' }}>
                  {{ $example }}
                </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <!-- Idioma -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-translate me-1"></i> Idioma</h5>
        </div>
        <div class="card-body">
          @php
              $activeLanguages = \Screenart\Musedock\Database::table('languages')
                  ->where('active', 1)->whereNull('tenant_id')
                  ->orderBy('order_position')->get();
          @endphp
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Idioma por defecto</label>
              <select name="default_lang" class="form-select">
                @foreach($activeLanguages as $lang)
                <option value="{{ $lang->code }}" {{ ($settings['default_lang'] ?? 'es') == $lang->code ? 'selected' : '' }}>{{ $lang->name }} ({{ $lang->code }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Forzar idioma</label>
              <select name="force_lang" class="form-select">
                <option value="">No forzar (detectar navegador)</option>
                @foreach($activeLanguages as $lang)
                <option value="{{ $lang->code }}" {{ ($settings['force_lang'] ?? '') == $lang->code ? 'selected' : '' }}>{{ $lang->name }} ({{ $lang->code }})</option>
                @endforeach
              </select>
              <small class="text-muted">Si se activa, todos los visitantes verán el sitio en este idioma.</small>
            </div>
            <div class="col-md-4 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="show_language_switcher" name="show_language_switcher" {{ ($settings['show_language_switcher'] ?? '1') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="show_language_switcher">Mostrar selector de idioma</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Código personalizado -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-code-slash me-1"></i> Código personalizado</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Código en &lt;head&gt;</label>
            <textarea name="custom_head_code" class="form-control font-monospace" rows="4" style="font-size: 0.85rem;" placeholder="<!-- Google Analytics, meta tags, etc. -->">{{ $settings['custom_head_code'] ?? '' }}</textarea>
            <small class="text-muted">Se insertará antes de &lt;/head&gt;. Útil para scripts de tracking, meta tags, etc.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Código al inicio de &lt;body&gt;</label>
            <textarea name="custom_body_start_code" class="form-control font-monospace" rows="3" style="font-size: 0.85rem;" placeholder="<!-- Google Tag Manager (noscript), etc. -->">{{ $settings['custom_body_start_code'] ?? '' }}</textarea>
            <small class="text-muted">Se insertará justo después de &lt;body&gt;.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Código al final de &lt;body&gt;</label>
            <textarea name="custom_body_end_code" class="form-control font-monospace" rows="4" style="font-size: 0.85rem;" placeholder="<!-- Scripts de chat, widgets, etc. -->">{{ $settings['custom_body_end_code'] ?? '' }}</textarea>
            <small class="text-muted">Se insertará antes de &lt;/body&gt;.</small>
          </div>
        </div>
      </div>

      <!-- Datos legales -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-shield-check me-1"></i> Datos legales (Aviso Legal / Privacidad)</h5>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">Estos datos se usan para generar automáticamente las páginas de Aviso Legal, Política de Privacidad, etc. Son independientes de los datos de contacto públicos del sitio.</p>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Jurisdicción</label>
              <select name="legal_jurisdiction" class="form-select" id="legal_jurisdiction">
                @php $jurisdiction = $settings['legal_jurisdiction'] ?? 'ES'; @endphp
                <optgroup label="Europa">
                  <option value="ES" @selected($jurisdiction === 'ES')>España (LSSI + RGPD + LOPD-GDD)</option>
                  <option value="EU" @selected($jurisdiction === 'EU')>Unión Europea / EEE (RGPD)</option>
                </optgroup>
                <optgroup label="América">
                  <option value="US" @selected($jurisdiction === 'US')>Estados Unidos (Terms of Service)</option>
                  <option value="BR" @selected($jurisdiction === 'BR')>Brasil (LGPD)</option>
                  <option value="MX" @selected($jurisdiction === 'MX')>México (LFPDPPP)</option>
                  <option value="AR" @selected($jurisdiction === 'AR')>Argentina (Ley 25.326)</option>
                  <option value="LATAM" @selected($jurisdiction === 'LATAM')>Otro país latinoamericano</option>
                </optgroup>
                <optgroup label="Otros">
                  <option value="OTHER" @selected($jurisdiction === 'OTHER')>Otra jurisdicción</option>
                </optgroup>
              </select>
              <small class="text-muted">Determina el marco legal de las páginas generadas.</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Tipo de titular</label>
              <select name="legal_entity_type" class="form-select" id="legal_entity_type">
                @php $entityType = $settings['legal_entity_type'] ?? 'personal'; @endphp
                <option value="personal" @selected($entityType === 'personal')>Persona física (blog personal)</option>
                <option value="autonomo" @selected($entityType === 'autonomo')>Autónomo / Freelance</option>
                <option value="empresa" @selected($entityType === 'empresa')>Empresa / Sociedad (S.L., S.A., LLC, etc.)</option>
              </select>
              <small class="text-muted">Nivel de detalle del aviso legal.</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Nombre o razón social</label>
              <input type="text" name="legal_name" class="form-control" value="{{ $settings['legal_name'] ?? '' }}" placeholder="Empresa Ejemplo S.L.">
              <small class="text-muted">Nombre legal real. Puede diferir del nombre del sitio.</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3" id="legal_nif_group">
              <label class="form-label" id="legal_nif_label">NIF / CIF</label>
              <input type="text" name="legal_nif" class="form-control" value="{{ $settings['legal_nif'] ?? '' }}" placeholder="B12345678" id="legal_nif_input">
              <small class="text-muted" id="legal_nif_help">Obligatorio para autónomos y empresas.</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Email legal / DPO</label>
              <input type="email" name="legal_email" class="form-control" value="{{ $settings['legal_email'] ?? '' }}" placeholder="legal@tudominio.com">
              <small class="text-muted">Para cuestiones legales/RGPD. Si vacío, usa el email de contacto.</small>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Domicilio legal</label>
              <textarea name="legal_address" class="form-control" rows="2" placeholder="Calle, número, CP, ciudad">{{ $settings['legal_address'] ?? '' }}</textarea>
              <small class="text-muted">Si vacío, usa la dirección de contacto.</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3" id="legal_registry_group">
              <label class="form-label" id="legal_registry_label">Datos registrales</label>
              <input type="text" name="legal_registry_data" class="form-control" value="{{ $settings['legal_registry_data'] ?? '' }}" placeholder="" id="legal_registry_input">
              <small class="text-muted" id="legal_registry_help">Solo para sociedades inscritas.</small>
            </div>

            <div class="col-md-4 mb-3" id="legal_authority_group">
              <label class="form-label">Autoridad de control</label>
              <input type="text" name="legal_supervisory_authority" class="form-control" value="{{ $settings['legal_supervisory_authority'] ?? '' }}" placeholder="" id="legal_authority_input">
              <small class="text-muted" id="legal_authority_help">Autoridad de protección de datos.</small>
            </div>

            <div class="col-md-4 mb-3">
              <div class="form-check mt-4 mb-2">
                <input type="checkbox" class="form-check-input" name="site_has_economic_activity" value="1" id="has_economic_activity" {{ ($settings['site_has_economic_activity'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="has_economic_activity">Actividad económica</label>
              </div>
              <div class="form-check mb-2" id="legal_eu_users_group">
                <input type="checkbox" class="form-check-input" name="legal_targets_eu" value="1" id="legal_targets_eu" {{ ($settings['legal_targets_eu'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="legal_targets_eu">Dirigido a usuarios de la UE</label>
              </div>
              <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="site_uses_analytics_cookies" value="1" id="uses_analytics_cookies" {{ ($settings['site_uses_analytics_cookies'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="uses_analytics_cookies">Usa cookies de analítica</label>
              </div>
              <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="site_has_user_registration" value="1" id="has_user_registration" {{ ($settings['site_has_user_registration'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="has_user_registration">Registro de usuarios</label>
              </div>
              <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="site_has_paid_services" value="1" id="has_paid_services" {{ ($settings['site_has_paid_services'] ?? '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="has_paid_services">Servicios de pago</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <script>
      document.addEventListener('DOMContentLoaded', function() {
          var entitySelect = document.getElementById('legal_entity_type');
          var jurisdictionSelect = document.getElementById('legal_jurisdiction');
          var registryGroup = document.getElementById('legal_registry_group');
          var nifGroup = document.getElementById('legal_nif_group');
          var euUsersGroup = document.getElementById('legal_eu_users_group');
          var authorityGroup = document.getElementById('legal_authority_group');
          var nifLabel = document.getElementById('legal_nif_label');
          var nifInput = document.getElementById('legal_nif_input');
          var nifHelp = document.getElementById('legal_nif_help');
          var registryLabel = document.getElementById('legal_registry_label');
          var registryInput = document.getElementById('legal_registry_input');
          var registryHelp = document.getElementById('legal_registry_help');
          var authorityInput = document.getElementById('legal_authority_input');
          var authorityHelp = document.getElementById('legal_authority_help');

          var defaultAuthorities = {
              'ES': 'Agencia Española de Protección de Datos (AEPD) — www.aepd.es',
              'BR': 'Autoridade Nacional de Proteção de Dados (ANPD) — www.gov.br/anpd',
              'MX': 'Instituto Nacional de Transparencia (INAI) — www.inai.org.mx',
              'AR': 'Agencia de Acceso a la Información Pública (AAIP)',
              'EU': '', 'US': '', 'LATAM': '', 'OTHER': ''
          };

          function updateLegalFields() {
              var entity = entitySelect.value;
              var jurisdiction = jurisdictionSelect.value;
              var isEU = (jurisdiction === 'ES' || jurisdiction === 'EU');
              var isUS = (jurisdiction === 'US');
              var isBR = (jurisdiction === 'BR');

              registryGroup.style.display = entity === 'empresa' ? '' : 'none';
              nifGroup.style.display = entity === 'personal' ? 'none' : '';
              euUsersGroup.style.display = isEU ? 'none' : '';
              authorityGroup.style.display = (isEU || isBR || jurisdiction === 'AR' || jurisdiction === 'MX') ? '' : 'none';

              var defaultAuth = defaultAuthorities[jurisdiction] || '';
              authorityInput.placeholder = defaultAuth || 'Autoridad de protección de datos';
              if (defaultAuth) authorityHelp.textContent = 'Vacío = ' + defaultAuth.split(' — ')[0];
              else if (jurisdiction === 'EU') { authorityHelp.textContent = 'Ej: CNIL, DPC, BfDI...'; authorityInput.placeholder = 'Data Protection Commission (DPC)'; }

              if (isUS) { nifLabel.textContent = 'EIN / Tax ID'; nifInput.placeholder = '12-3456789'; registryLabel.textContent = 'State of Formation'; registryInput.placeholder = 'State of New Mexico, Filing No. 1234567'; }
              else if (isBR) { nifLabel.textContent = 'CNPJ / CPF'; nifInput.placeholder = '12.345.678/0001-00'; }
              else if (jurisdiction === 'MX') { nifLabel.textContent = 'RFC'; nifInput.placeholder = 'XAXX010101000'; }
              else { nifLabel.textContent = 'NIF / CIF'; nifInput.placeholder = 'B12345678'; registryLabel.textContent = 'Datos registrales'; registryInput.placeholder = 'Registro Mercantil de Madrid, Tomo X, Folio X, Hoja X'; }
          }

          entitySelect.addEventListener('change', updateLegalFields);
          jurisdictionSelect.addEventListener('change', updateLegalFields);
          updateLegalFields();
      });
      </script>

      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-2"></i>Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Script para eliminar logo con SweetAlert2 -->
@if(!empty($settings['site_logo']))
<script>
document.getElementById('delete-logo-btn').addEventListener('click', function() {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Esto eliminará el logotipo actual.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "{{ route('settings.delete_logo') }}";
    }
  })
});
</script>
@if(!empty($settings['site_favicon']))
<script>
document.getElementById('delete-favicon-btn').addEventListener('click', function() {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Esto eliminará el favicon actual.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "{{ route('settings.delete_favicon') }}";
    }
  })
});
</script>
@endif

@endif
@endsection
