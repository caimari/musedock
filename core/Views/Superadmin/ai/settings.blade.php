@extends('layouts.app')

@section('title', 'Configuración de AI Writer')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Configuración de AI Writer (Global)</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ajustes Globales</h6>
            <small>Estos ajustes se aplicarán a menos que un tenant los sobrescriba.</small>
        </div>
        <div class="card-body">
            <form action="/musedock/aiwriter/settings/update" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="default_provider" class="form-label fw-bold">Proveedor por Defecto para AI Writer</label>
                    <select class="form-select" id="default_provider" name="default_provider">
                        <option value="">-- Seleccionar Proveedor --</option>
                        @if(!empty($providers))
                            @foreach($providers as $provider)
                                <option value="{{ $provider['id'] }}" {{ isset($settings['ai_default_provider']) && $settings['ai_default_provider'] == $provider['id'] ? 'selected' : '' }}>
                                    {{ $provider['name'] }}
                                </option>
                            @endforeach
                        @else
                            <option value="" disabled>No hay proveedores activos disponibles</option>
                        @endif
                    </select>
                    <div class="form-text">Proveedor a usar por defecto en las acciones rápidas del editor.</div>
                </div>

                <div class="mb-3">
                    <label for="daily_token_limit" class="form-label fw-bold">Límite Diario Global de Tokens por Usuario</label>
                    <input type="number" class="form-control" id="daily_token_limit" name="daily_token_limit" 
                           value="{{ isset($settings['ai_daily_token_limit']) ? $settings['ai_daily_token_limit'] : '0' }}" min="0">
                    <div class="form-text">Límite global (0 = sin límite). Puede ser sobrescrito por tenant.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="log_all_prompts" name="log_all_prompts" value="1"
                               {{ isset($settings['ai_log_all_prompts']) && $settings['ai_log_all_prompts'] == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="log_all_prompts">Guardar Prompts Completos Globalmente</label>
                    </div>
                    <div class="form-text">Define si se guardan los prompts completos en los logs para todos.</div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Configuración Global</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection