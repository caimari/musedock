@extends('layouts.app')

@section('title', 'Nuevo Proveedor de IA')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Crear Nuevo Proveedor de IA</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="/musedock/ai/providers/store" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Nombre del Proveedor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="provider_type" class="form-label">Tipo de Proveedor <span class="text-danger">*</span></label>
                        <select class="form-select" id="provider_type" name="provider_type" required>
                            <option value="openai" {{ old('provider_type') == 'openai' ? 'selected' : '' }}>OpenAI</option>
                            <option value="claude" {{ old('provider_type') == 'claude' ? 'selected' : '' }}>Claude (Anthropic)</option>
                            <option value="gemini" {{ old('provider_type') == 'gemini' ? 'selected' : '' }}>Gemini (Google)</option>
                            <option value="ollama" {{ old('provider_type') == 'ollama' ? 'selected' : '' }}>Ollama (Local)</option>
                            <option value="minimax" {{ old('provider_type') == 'minimax' ? 'selected' : '' }}>MiniMax</option>
                            <option value="other" {{ old('provider_type') == 'other' ? 'selected' : '' }}>Otro</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="api_key" class="form-label">API Key</label>
                    <input type="password" class="form-control" id="api_key" name="api_key" placeholder="Dejar en blanco si no aplica o para mantenerla segura">
                    <div class="form-text">Introduce la clave API proporcionada por el proveedor.</div>
                </div>

                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label">Modelo por Defecto</label>
                        <input type="text" class="form-control" id="model" name="model" value="{{ old('model') }}" placeholder="Ej: gpt-4, claude-3-opus...">
                        <div class="form-text" id="model-suggestions"></div>
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="endpoint" class="form-label">Endpoint (Opcional)</label>
                        <input type="url" class="form-control" id="endpoint" name="endpoint" value="{{ old('endpoint') }}" placeholder="URL de la API si es diferente a la estandar">
                        <div class="form-text" id="endpoint-hint"></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="temperature" class="form-label">Temperatura</label>
                        <input type="number" step="0.1" min="0" max="2" class="form-control" id="temperature" name="temperature" value="{{ old('temperature', 0.7) }}">
                        <div class="form-text">Controla la creatividad (0 = determinista, 1 = creativo).</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="max_tokens" class="form-label">Max Tokens</label>
                        <input type="number" step="1" min="1" class="form-control" id="max_tokens" name="max_tokens" value="{{ old('max_tokens', 1000) }}">
                         <div class="form-text">Límite máximo de tokens por respuesta.</div>
                    </div>
                </div>

                 <div class="row mb-3">
                    <div class="col-md-4">
                         <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" {{ old('active') ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">Activo</label>
                        </div>
                        <div class="form-text">Permite el uso de este proveedor.</div>
                    </div>
                     <div class="col-md-4">
                         <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="system_wide" name="system_wide" value="1" {{ old('system_wide') ? 'checked' : '' }}>
                            <label class="form-check-label" for="system_wide">Disponible Globalmente</label>
                        </div>
                         <div class="form-text">Si está marcado, los tenants pueden usarlo (si está activo).</div>
                    </div>
                    <div class="col-md-4">
                         <label for="tenant_id" class="form-label">Tenant ID (Opcional)</label>
                        <input type="number" class="form-control" id="tenant_id" name="tenant_id" value="{{ old('tenant_id') }}" placeholder="ID numérico del tenant">
                         <div class="form-text">Asigna este proveedor exclusivamente a un tenant específico (dejar en blanco si es global).</div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Proveedor</button>
                    <a href="/musedock/ai/providers" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var providerSelect = document.getElementById('provider_type');
    var modelInput = document.getElementById('model');
    var suggestionsDiv = document.getElementById('model-suggestions');
    var endpointInput = document.getElementById('endpoint');
    var endpointHint = document.getElementById('endpoint-hint');

    var providerModels = {
        openai: {
            models: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1', 'o1-mini'],
            defaultModel: 'gpt-4o',
            endpoint: ''
        },
        claude: {
            models: ['claude-sonnet-4-20250514', 'claude-haiku-4-20250414', 'claude-opus-4-20250514'],
            defaultModel: 'claude-sonnet-4-20250514',
            endpoint: ''
        },
        gemini: {
            models: ['gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro', 'gemini-1.5-flash'],
            defaultModel: 'gemini-2.0-flash',
            endpoint: ''
        },
        ollama: {
            models: ['llama3.1', 'llama3.2', 'mistral', 'gemma2', 'qwen2.5'],
            defaultModel: 'llama3.1',
            endpoint: 'http://localhost:11434/api/chat'
        },
        minimax: {
            models: ['MiniMax-M2.5', 'MiniMax-M2.5-highspeed', 'MiniMax-M2.1', 'MiniMax-M2.1-highspeed', 'MiniMax-M2'],
            defaultModel: 'MiniMax-M2.5',
            endpoint: 'https://api.minimax.io/v1/chat/completions'
        },
        other: {
            models: [],
            defaultModel: '',
            endpoint: ''
        }
    };

    function updateSuggestions() {
        var type = providerSelect.value;
        var info = providerModels[type];
        if (info && info.models.length > 0) {
            var links = info.models.map(function(m) {
                return '<a href="#" class="model-pick" data-model="' + m + '">' + m + '</a>';
            });
            suggestionsDiv.innerHTML = 'Modelos: ' + links.join(' · ');
        } else {
            suggestionsDiv.innerHTML = '';
        }
        if (info && info.endpoint) {
            endpointHint.innerHTML = 'Endpoint por defecto: <code>' + info.endpoint + '</code>';
        } else {
            endpointHint.innerHTML = '';
        }
    }

    providerSelect.addEventListener('change', function() {
        var type = this.value;
        var info = providerModels[type];
        if (info && info.defaultModel && !modelInput.value) {
            modelInput.value = info.defaultModel;
        }
        if (info && info.endpoint && !endpointInput.value) {
            endpointInput.value = info.endpoint;
        }
        updateSuggestions();
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('model-pick')) {
            e.preventDefault();
            modelInput.value = e.target.getAttribute('data-model');
        }
    });

    updateSuggestions();
});
</script>
@endpush
@endsection