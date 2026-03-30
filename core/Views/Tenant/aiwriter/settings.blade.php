@extends('layouts.app') {{-- Asegúrate que este sea el layout correcto del admin --}}

@section('title', 'Configuración de AI Writer')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Configuración de AI Writer</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
         <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ajustes para tu Sitio</h6>
             <small>Estos ajustes sobrescriben la configuración global.</small>
        </div>
        <div class="card-body">
             {{-- Genera la URL admin dinámicamente --}}
             <form action="{{ admin_url('/aiwriter/settings/update') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="default_provider" class="form-label">Proveedor por Defecto para AI Writer</label>
                    <select class="form-select" id="default_provider" name="default_provider">
                         @if(empty($providers))
                            <option value="">No hay proveedores activos disponibles</option>
                         @else
                            @foreach($providers as $provider)
                                <option value="{{ $provider['id'] }}" {{ old('default_provider', $settings['default_provider']) == $provider['id'] ? 'selected' : '' }}>
                                    {{ $provider['name'] }} {{ $provider['tenant_id'] === null ? '(Global)' : '' }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <div class="form-text">Proveedor a usar por defecto en las acciones rápidas del editor en este sitio.</div>
                </div>

                 <div class="mb-3">
                    <label for="daily_token_limit" class="form-label">Límite Diario de Tokens por Usuario (para este sitio)</label>
                    <input type="number" class="form-control" id="daily_token_limit" name="daily_token_limit" value="{{ old('daily_token_limit', $settings['daily_token_limit']) }}" min="0">
                    <div class="form-text">Límite para los usuarios de este sitio (0 = usar límite global o sin límite).</div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="log_all_prompts" name="log_all_prompts" value="1" {{ old('log_all_prompts', $settings['log_all_prompts']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="log_all_prompts">Guardar Prompts Completos (para este sitio)</label>
                    </div>
                     <div class="form-text">Define si se guardan los prompts completos en los logs para los usuarios de este sitio.</div>
                </div>

                 {{-- Sección de Permisos (Opcional, si quieres gestionar aquí) --}}
                 {{--
                 <hr>
                 <h6 class="mb-3">Permisos por Rol (en este sitio)</h6>
                 @php $tenantId = tenant_id(); @endphp
                 @forelse ($roles as $role)
                    @if($role['tenant_id'] === $tenantId) {{-- Solo roles de este tenant --}}
                         {{--
                         <div class="mb-2">
                             <strong>{{ $role['name'] }}:</strong>
                             <div class="form-check form-check-inline ms-2">
                                 <input class="form-check-input" type="checkbox" id="perm_ai_use_{{ $role['id'] }}" name="role_perms[{{ $role['id'] }}][ai.use]" value="1" {{-- Lógica para marcar si tiene permiso --}}>
                                 {{--<label class="form-check-label" for="perm_ai_use_{{ $role['id'] }}">Permitir Uso de IA</label>
                             {{--</div>
                         {{--</div>
                     {{--@endif
                 {{--@empty
                     <p>No hay roles definidos para este sitio.</p>
                 {{--@endforelse
                 --}}

                 <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Configuración del Sitio</button>
                </div>

             </form>
        </div>
    </div>
</div>
@endsection