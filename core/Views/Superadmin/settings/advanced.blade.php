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
          <div id="update-log" class="mb-3" style="display: none;">
            <pre style="background:#1a1a2e;color:#94a3b8;padding:12px;border-radius:6px;max-height:300px;overflow-y:auto;font-size:0.8rem;" id="update-log-content"></pre>
          </div>

          <div class="d-flex gap-2">
            <button type="button" id="checkUpdatesBtn" class="btn btn-outline-primary">
              <i class="bi bi-arrow-repeat me-1"></i> {{ __('settings.check_updates') }}
            </button>
            <button type="button" id="runUpdateBtn" class="btn btn-success" style="display: none;">
              <i class="bi bi-download me-1"></i> Actualizar ahora
            </button>
            <a href="{{ $versionInfo['repository'] ?? '#' }}" target="_blank" class="btn btn-outline-secondary">
              <i class="bi bi-github me-1"></i> GitHub
            </a>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Firewall y Let's Encrypt</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">Diagnóstico ACME, validación de proveedores DNS y apertura temporal de puertos 80/443 con confirmación de contraseña.</p>
          <a href="/musedock/settings/acme-assistant" class="btn btn-outline-primary">
            <i class="bi bi-tools me-1"></i>Abrir Asistente ACME
          </a>
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
              <label class="form-label">{{ __('settings.site_language') }}</label>
              <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle me-1"></i>
                La configuración del idioma del sitio se ha movido a la sección de
                <a href="{{ route('languages.index') }}" class="alert-link">
                  <i class="bi bi-globe me-1"></i>{{ __('menu.languages') }}
                </a>
              </div>
            </div>

            <button class="btn btn-primary">{{ __('common.save') }}</button>
          </form>

          <hr>

          {{-- Botón para limpiar la caché de Blade, Sitemaps y Feeds --}}
          <div class="mt-4">
            <button id="clearCacheButton" class="btn btn-outline-danger w-100">
              <i class="bi bi-trash me-1"></i> {{ __('settings.clear_blade_cache') }}
            </button>
            <small class="text-muted d-block mt-1">Limpia la cache de vistas Blade, sitemaps XML y feeds RSS de todos los tenants.</small>
          </div>

          {{-- Botón para limpiar OPcache de PHP --}}
          <div class="mt-3">
            <button id="clearOpcacheButton" class="btn btn-outline-warning w-100">
              <i class="bi bi-lightning-charge me-1"></i> Limpiar OPcache de PHP
            </button>
            <small class="text-muted d-block mt-1">Fuerza la recarga de todos los archivos PHP compilados en memoria. Útil tras actualizar código.</small>
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
              </div>
              ${data.changelog ? `<hr class="my-2"><small class="text-muted">${data.changelog.substring(0, 200)}...</small>` : ''}
            </div>
          `;
          resultDiv.innerHTML = updateHtml;
          resultDiv.style.display = 'block';
          document.getElementById('runUpdateBtn').style.display = 'inline-block';
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

  // Run CMS Update
  document.getElementById('runUpdateBtn').addEventListener('click', function() {
    const btn = this;
    const logDiv = document.getElementById('update-log');
    const logContent = document.getElementById('update-log-content');
    const statusDiv = document.getElementById('update-status');

    Swal.fire({
      title: 'Actualizar MuseDock CMS?',
      text: 'Se descargará la última versión desde GitHub, se ejecutarán migraciones y se limpiará la caché.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: '<i class="bi bi-download me-1"></i> Actualizar',
      confirmButtonColor: '#28a745',
      cancelButtonText: '{{ __("common.cancel") }}'
    }).then(function(result) {
      if (!result.isConfirmed) return;

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Actualizando...';
      statusDiv.innerHTML = '<span class="badge bg-info"><i class="bi bi-arrow-repeat me-1"></i>Actualizando...</span>';
      logDiv.style.display = 'block';
      logContent.textContent = 'Iniciando actualización...\n';

      fetch('/musedock/settings/run-update', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
      })
      .then(r => r.json())
      .then(function(data) {
        if (!data.success) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-download me-1"></i> Actualizar ahora';
          logContent.textContent += 'ERROR: ' + (data.error || data.message) + '\n';
          return;
        }

        // Poll for status
        var pollInterval = setInterval(function() {
          fetch('/musedock/settings/update-status')
          .then(r => r.json())
          .then(function(s) {
            if (s.output) logContent.textContent = s.output;
            logDiv.scrollTop = logDiv.scrollHeight;

            if (!s.in_progress) {
              clearInterval(pollInterval);
              btn.style.display = 'none';
              statusDiv.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Actualizado a v' + (s.version || '?') + '</span>';
              logContent.textContent += '\n✓ Actualización completada. Recargando...\n';
              setTimeout(function() { window.location.reload(); }, 2000);
            }
          })
          .catch(function() {});
        }, 1500);
      })
      .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download me-1"></i> Actualizar ahora';
        logContent.textContent += 'Error de red: ' + err.message + '\n';
      });
    });
  });

  // Limpiar OPcache de PHP
  document.getElementById('clearOpcacheButton').addEventListener('click', function (e) {
    e.preventDefault();
    Swal.fire({
      title: '¿Limpiar OPcache?',
      text: 'Se recargará en memoria el código PHP del servidor. No afecta a los usuarios activos.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#f0ad4e',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sí, limpiar',
      cancelButtonText: '{{ __("common.cancel") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = "{{ route('settings.advanced.clearOpcache') }}";
      }
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
