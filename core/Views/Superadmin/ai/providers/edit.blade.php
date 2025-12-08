@extends('layouts.app')

@section('title', 'Editar Proveedor de IA: ' . $provider['name'])

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Editar Proveedor: {{ $provider['name'] }}</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="/musedock/ai/providers/{{ $provider['id'] }}/update" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label fw-bold">Nombre Identificativo del Proveedor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $provider['name']) }}" required>
                        <div class="form-text">Nombre personalizado para identificar este proveedor (ej. "OpenAI GPT-4 16K", "Claude Opus")</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="provider_type" class="form-label fw-bold">Plataforma de IA <span class="text-danger">*</span></label>
                        <select class="form-select" id="provider_type" name="provider_type" required>
                            <option value="openai" {{ old('provider_type', $provider['provider_type']) == 'openai' ? 'selected' : '' }}>OpenAI</option>
                            <option value="claude" {{ old('provider_type', $provider['provider_type']) == 'claude' ? 'selected' : '' }}>Claude (Anthropic)</option>
                            <option value="gemini" {{ old('provider_type', $provider['provider_type']) == 'gemini' ? 'selected' : '' }}>Gemini (Google)</option>
                            <option value="other" {{ old('provider_type', $provider['provider_type']) == 'other' ? 'selected' : '' }}>Otro</option>
                        </select>
                        <div class="form-text">Selecciona la empresa/plataforma proveedora del servicio de IA</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="api_key" class="form-label fw-bold">Clave API</label>
                    <input type="password" class="form-control" id="api_key" name="api_key" placeholder="••••••••••">
                    <div class="form-text">Introduce una nueva clave API sólo si quieres actualizarla. Dejar en blanco para mantener la guardada.</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label fw-bold">Modelo Específico</label>
                        <input type="text" class="form-control" id="model" name="model" value="{{ old('model', $provider['model']) }}" placeholder="Ej: gpt-4, claude-3-opus, gemini-pro...">
                        <div class="form-text">Nombre técnico del modelo de IA específico que quieres utilizar</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="endpoint" class="form-label fw-bold">Endpoint Personalizado (Opcional)</label>
                        <input type="url" class="form-control" id="endpoint" name="endpoint" value="{{ old('endpoint', $provider['endpoint']) }}" placeholder="https://api.example.com/v1/chat/completions">
                        <div class="form-text">URL específica de la API, solo necesario si no usas la URL estándar</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="temperature" class="form-label fw-bold">Temperatura (Creatividad)</label>
                        <input type="number" step="0.1" min="0" max="2" class="form-control" id="temperature" name="temperature" value="{{ old('temperature', $provider['temperature']) }}">
                        <div class="form-text">Controla la aleatoriedad/creatividad (0 = predecible y conservador, 1 = creativo, 2 = muy creativo)</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="max_tokens" class="form-label fw-bold">Límite de Tokens por Respuesta</label>
                        <input type="number" step="1" min="1" class="form-control" id="max_tokens" name="max_tokens" value="{{ old('max_tokens', $provider['max_tokens']) }}">
                        <div class="form-text">Cantidad máxima de tokens que el modelo puede generar en cada respuesta</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" {{ old('active', $provider['active']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="active">Activo</label>
                        </div>
                        <div class="form-text">Habilita/deshabilita el uso de este proveedor en el sistema</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="system_wide" name="system_wide" value="1" {{ old('system_wide', $provider['system_wide']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="system_wide">Disponible Globalmente</label>
                        </div>
                        <div class="form-text">Si está marcado, todos los tenants pueden usar este proveedor (si está activo)</div>
                    </div>
                    <div class="col-md-4">
                        <label for="tenant_id" class="form-label fw-bold">Asignar a Tenant Específico</label>
                        <select class="form-select" id="tenant_id" name="tenant_id">
                            <option value="" {{ old('tenant_id', $provider['tenant_id']) === null ? 'selected' : '' }}>Global (Disponible para todos)</option>
                            
                            @php
                            try {
                                $tenants = \Screenart\Musedock\Database::query("SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name")->fetchAll();
                            } catch (\Exception $e) {
                                $tenants = [];
                            }
                            @endphp
                            
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant['id'] }}" {{ old('tenant_id', $provider['tenant_id']) == $tenant['id'] ? 'selected' : '' }}>
                                    {{ $tenant['name'] }} ({{ $tenant['domain'] }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Asigna este proveedor exclusivamente a un tenant específico (dejar en "Global" para que sea compartido)</div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Proveedor</button>
                    <a href="/musedock/ai/providers" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection