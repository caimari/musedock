@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Ajustes avanzados</h3>
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
              <strong>ℹ️ Configuración desde .env</strong><br>
              La multitenencia está configurada en el archivo <code>.env</code> y no se puede cambiar desde esta interfaz.
              Para modificarla, edita el archivo <code>.env</code> en el servidor.
            </div>
          @endif

          @if ($currentMultiTenant && $currentMainDomain)
            <div class="alert alert-warning">
              ⚠️ La multitenencia está activada. Dominio principal: <strong>{{ $currentMainDomain }}</strong>
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
                Habilitar multitenencia
                @if ($isEnvConfigured)
                  <small class="text-muted">(Configurado en .env)</small>
                @endif
              </label>
              @if (!$isEnvConfigured)
                <small class="form-text text-muted">
                  ⚠️ Recomendado: configurar en .env para mayor seguridad
                </small>
              @endif
            </div>

            @if (!$isEnvConfigured)
              <div class="mb-3">
                <label>Dominio principal (requerido si multitenencia está activa)</label>
                <input
                  type="text"
                  name="main_domain"
                  class="form-control"
                  placeholder="ejemplo.com"
                  value="{{ $currentMainDomain ?? '' }}"
                >
                <small class="form-text text-muted">
                  Este dominio mostrará el panel /musedock y el sitio principal
                </small>
              </div>
            @else
              @if ($currentMainDomain)
                <div class="mb-3">
                  <label>Dominio principal</label>
                  <input
                    type="text"
                    class="form-control"
                    value="{{ $currentMainDomain }}"
                    disabled
                  >
                  <small class="form-text text-muted">Configurado en .env</small>
                </div>
              @endif
            @endif

            <div class="mb-3">
              <label class="form-label">
                Idioma del sitio
                <small class="text-muted">(Gestiona idiomas disponibles en <a href="{{ route('settings.languages') }}">Configuración → Idiomas</a>)</small>
              </label>
              <select name="force_lang" class="form-select">
                <option value="">Detección automática (según navegador del usuario)</option>
                @php
                  $languages = \Screenart\Musedock\Database::table('languages')->where('active', 1)->get();
                @endphp
                @foreach($languages as $lang)
                  <option value="{{ $lang->code }}" {{ ($settings['force_lang'] ?? '') === $lang->code ? 'selected' : '' }}>
                    Forzar {{ $lang->name }} ({{ $lang->code }})
                  </option>
                @endforeach
              </select>
              <small class="form-text text-muted">
                Si seleccionas un idioma específico, todos los visitantes verán el sitio en ese idioma, independientemente de su navegador.
              </small>
            </div>

            <button class="btn btn-primary">Guardar cambios</button>
          </form>

          <hr>

          {{-- Botón para limpiar la caché de Blade --}}
          <div class="mt-4">
            <button id="clearCacheButton" class="btn btn-outline-danger w-100">
              Borrar caché de vistas Blade
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
document.getElementById('clearCacheButton').addEventListener('click', function (e) {
  e.preventDefault();

  Swal.fire({
    title: '¿Borrar caché de vistas Blade?',
    text: "Esta acción eliminará todos los archivos cacheados de las plantillas Blade.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, borrar caché',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "{{ route('settings.advanced.clearBladeCache') }}";
    }
  });
});
</script>
@endpush
