@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6">

      {{-- Card Versión del Sistema --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0"><i class="bi bi-box-seam me-2"></i>{{ __('settings.version_info') }}</h5>
          <span class="badge bg-primary fs-6">v{{ $versionInfo['current'] ?? '0.0.0' }}</span>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <strong>{{ __('settings.installed_version') }}:</strong>
              <code class="ms-2">{{ $versionInfo['current'] ?? '0.0.0' }}</code>
            </div>
            <div id="update-status"></div>
          </div>

          <div id="update-result" class="mb-3" style="display: none;"></div>

          <div class="d-flex gap-2">
            <button type="button" id="checkUpdatesBtn" class="btn btn-outline-primary">
              <i class="bi bi-arrow-repeat me-1"></i> {{ __('settings.check_updates') }}
            </button>
            <a href="{{ $versionInfo['repository'] ?? '#' }}" target="_blank" class="btn btn-outline-secondary">
              <i class="bi bi-github me-1"></i> GitHub
            </a>
          </div>
        </div>
      </div>

      {{-- Card Ajustes Avanzados --}}
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">{{ __('settings.advanced_title') }}</h3>
        </div>
        <div class="card-body">

          @php
            // Verificar si multitenencia está configurada en .env
            $envMultiTenant = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
            $envMainDomain = \Screenart\Musedock\Env::get('MAIN_DOMAIN', null);
            $isEnvConfigured = $envMultiTenant !== null;

            // Obtener valor actual (desde .env o BD)
            $currentMultiTenant = $envMultiTenant ?? ($settings['multi_tenant_enabled'] ?? 0);
            $currentMainDomain = $envMainDomain ?? config('main_domain');
          @endphp

          @if ($isEnvConfigured)
            <div class="alert alert-info">
              <strong><i class="bi bi-info-circle me-1"></i> {{ __('settings.env_configured') }}</strong><br>
              {{ __('settings.multitenancy_env_notice') }}
            </div>
          @endif

          @if ($currentMultiTenant && $currentMainDomain)
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle me-1"></i> {{ __('settings.multitenancy_active') }}: <strong>{{ $currentMainDomain }}</strong>
            </div>
          @endif

          <form method="POST" action="{{ route('settings.advanced.update') }}">
            {!! csrf_field() !!}

            <div class="form-check form-switch mb-3">
              <input
                class="form-check-input"
                type="checkbox"
                name="multi_tenant_enabled"
                value="1"
                {{ $currentMultiTenant ? 'checked' : '' }}
                {{ $isEnvConfigured ? 'disabled' : '' }}
              >
              <label class="form-check-label">
                {{ __('settings.enable_multitenancy') }}
                @if ($isEnvConfigured)
                  <small class="text-muted">({{ __('settings.configured_in_env') }})</small>
                @endif
              </label>
              @if (!$isEnvConfigured)
                <small class="form-text text-muted">
                  <i class="bi bi-exclamation-triangle me-1"></i> {{ __('settings.recommend_env') }}
                </small>
              @endif
            </div>

            @if (!$isEnvConfigured)
              <div class="mb-3">
                <label>{{ __('settings.main_domain') }}</label>
                <input
                  type="text"
                  name="main_domain"
                  class="form-control"
                  placeholder="ejemplo.com"
                  value="{{ $currentMainDomain ?? '' }}"
                >
                <small class="form-text text-muted">
                  {{ __('settings.main_domain_help') }}
                </small>
              </div>
            @else
              @if ($currentMainDomain)
                <div class="mb-3">
                  <label>{{ __('settings.main_domain') }}</label>
                  <input
                    type="text"
                    class="form-control"
                    value="{{ $currentMainDomain }}"
                    disabled
                  >
                  <small class="form-text text-muted">{{ __('settings.configured_in_env') }}</small>
                </div>
              @endif
            @endif

            <div class="mb-3">
              <label class="form-label">
                {{ __('settings.site_language') }}
                <small class="text-muted">({{ __('settings.manage_languages_link') }} <a href="{{ route('settings.languages') }}">{{ __('menu.settings') }} → {{ __('menu.languages') }}</a>)</small>
              </label>
              <select name="force_lang" class="form-select">
                <option value="">{{ __('settings.auto_detect_language') }}</option>
                @php
                  $languages = \Screenart\Musedock\Database::table('languages')->where('active', 1)->get();
                @endphp
                @foreach($languages as $lang)
                  <option value="{{ $lang->code }}" {{ ($settings['force_lang'] ?? '') === $lang->code ? 'selected' : '' }}>
                    {{ __('settings.force_language') }} {{ $lang->name }} ({{ $lang->code }})
                  </option>
                @endforeach
              </select>
              <small class="form-text text-muted">
                {{ __('settings.force_language_help') }}
              </small>
            </div>

            <button class="btn btn-primary">{{ __('common.save') }}</button>
          </form>

          <hr>

          {{-- Botón para limpiar la caché de Blade --}}
          <div class="mt-4">
            <button id="clearCacheButton" class="btn btn-outline-danger w-100">
              <i class="bi bi-trash me-1"></i> {{ __('settings.clear_blade_cache') }}
            </button>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

  // Verificar actualizaciones
  document.getElementById('checkUpdatesBtn').addEventListener('click', function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    const resultDiv = document.getElementById('update-result');
    const statusDiv = document.getElementById('update-status');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> {{ __("settings.checking") }}...';
    resultDiv.style.display = 'none';

    fetch('{{ route("settings.check-updates") }}', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      // Verificar si la respuesta es JSON válido
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        // Si no es JSON, obtener el texto para mostrar el error real
        return response.text().then(text => {
          throw new Error('El servidor devolvió HTML en lugar de JSON. Respuesta: ' + text.substring(0, 200));
        });
      }
      return response.json();
    })
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = originalHtml;

      if (data.success) {
        if (data.has_update) {
          statusDiv.innerHTML = '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>{{ __("settings.update_available") }}</span>';

          let updateHtml = `
            <div class="alert alert-warning mb-0">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <strong><i class="bi bi-arrow-up-circle me-1"></i> {{ __("settings.new_version_available") }}</strong><br>
                  <small>{{ __("settings.current") }}: <code>${data.current_version}</code> → {{ __("settings.latest") }}: <code>${data.latest_version}</code></small>
                </div>
                ${data.download_url && data.source !== 'packagist' ?
                  `<a href="${data.download_url}" target="_blank" class="btn btn-sm btn-warning">
                    <i class="bi bi-download me-1"></i> {{ __("settings.view_release") }}
                  </a>` :
                  `<code class="bg-dark text-light p-2 rounded">${data.download_url}</code>`
                }
              </div>
              ${data.changelog ? `<hr class="my-2"><small class="text-muted">${data.changelog.substring(0, 200)}...</small>` : ''}
            </div>
          `;
          resultDiv.innerHTML = updateHtml;
          resultDiv.style.display = 'block';
        } else {
          statusDiv.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>{{ __("settings.up_to_date") }}</span>';

          resultDiv.innerHTML = `
            <div class="alert alert-success mb-0">
              <i class="bi bi-check-circle me-1"></i> {{ __("settings.running_latest") }}
              <br><small class="text-muted">{{ __("settings.source") }}: ${data.source || 'N/A'}</small>
            </div>
          `;
          resultDiv.style.display = 'block';
        }
      } else {
        // Manejar diferentes tipos de errores JSON
        let alertClass = 'secondary';
        let alertIcon = 'info-circle';

        // Si es error de autenticación, mostrar alerta diferente
        if (data.error && data.error.includes('autenticado')) {
          alertClass = 'warning';
          alertIcon = 'exclamation-triangle';
        } else if (data.error && data.error.includes('permiso')) {
          alertClass = 'danger';
          alertIcon = 'x-circle';
        }

        statusDiv.innerHTML = `<span class="badge bg-${alertClass}"><i class="bi bi-${alertIcon} me-1"></i>{{ __("settings.check_failed") }}</span>`;

        // Mostrar información de debug si existe
        let debugInfo = '';
        if (data.debug && (data.debug.previous_output || data.debug.buffer_output)) {
          debugInfo = `<hr><small class="text-muted"><strong>Debug:</strong><br>`;
          if (data.debug.previous_output) debugInfo += `Previous: ${data.debug.previous_output}<br>`;
          if (data.debug.buffer_output) debugInfo += `Buffer: ${data.debug.buffer_output}`;
          debugInfo += `</small>`;
        }
        if (data.error_file) {
          debugInfo += `<br><small class="text-muted">File: ${data.error_file}</small>`;
        }

        resultDiv.innerHTML = `
          <div class="alert alert-${alertClass} mb-0">
            <i class="bi bi-${alertIcon} me-1"></i> {{ __("settings.could_not_check") }}
            <br><small class="text-muted">${data.error || '{{ __("settings.no_response") }}'}</small>
            ${debugInfo}
          </div>
        `;
        resultDiv.style.display = 'block';

        // Log completo en consola
        console.warn('Verificación de actualizaciones fallida:', data);
      }
    })
    .catch(error => {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
      statusDiv.innerHTML = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Error</span>';

      // Mostrar error completo incluyendo información de debug si existe
      let errorHtml = `<div class="alert alert-danger mb-0">
        <strong>Error:</strong> ${error.message}
      </div>`;

      // Log completo en consola para depuración
      console.error('Error verificando actualizaciones:', error);

      resultDiv.innerHTML = errorHtml;
      resultDiv.style.display = 'block';
    });
  });

  // Limpiar caché de Blade
  document.getElementById('clearCacheButton').addEventListener('click', function (e) {
    e.preventDefault();

    Swal.fire({
      title: '{{ __("settings.clear_cache_confirm_title") }}',
      text: '{{ __("settings.clear_cache_confirm_text") }}',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '{{ __("settings.yes_clear_cache") }}',
      cancelButtonText: '{{ __("common.cancel") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = "{{ route('settings.advanced.clearBladeCache') }}";
      }
    });
  });

});
</script>
@endpush
