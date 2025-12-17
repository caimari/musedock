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
