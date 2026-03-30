@extends('layouts.app')

@section('title', 'Configuración de IA')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Configuración de IA</h4>
            <a href="/{{ admin_path() }}/ai/auto-tagger" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-tags"></i> Auto-Categorizar Posts
            </a>
        </div>

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @php unset($_SESSION['flash_success']); @endphp
        @endif
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @php unset($_SESSION['flash_error']); @endphp
        @endif

        {{-- Estado actual --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="bi bi-info-circle"></i> Estado actual</h6>
                @if($tenantProvider)
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>Usando tu propia API Key.</strong>
                        Tus llamadas a IA usan tu clave de {{ ucfirst($tenantProvider['provider_type']) }} directamente. No consumes cuota del sistema.
                    </div>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ ucfirst($tenantProvider['provider_type']) }}</div>
                                <small class="text-muted">Proveedor</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ $tenantProvider['model'] }}</div>
                                <small class="text-muted">Modelo</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ number_format($todayTokens) }}</div>
                                <small class="text-muted">Tokens hoy</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ $todayRequests }}</div>
                                <small class="text-muted">Peticiones hoy</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Usando el proveedor del sistema.</strong>
                        Las llamadas a IA usan la API Key del administrador del sistema.
                        @if($quota['limit'] > 0)
                            Tu cuota diaria es de <strong>{{ number_format($quota['limit']) }} tokens</strong>.
                            Hoy has usado <strong>{{ number_format($todayTokens) }}</strong> ({{ $quota['limit'] > 0 ? round(($todayTokens / $quota['limit']) * 100) : 0 }}%).
                        @else
                            Actualmente no tienes límite de cuota asignado.
                        @endif
                    </div>
                    @if($quota['limit'] > 0)
                    <div class="progress mb-3" style="height: 20px;">
                        @php
                            $pct = min(100, round(($todayTokens / $quota['limit']) * 100));
                            $barClass = $pct < 70 ? 'bg-success' : ($pct < 90 ? 'bg-warning' : 'bg-danger');
                        @endphp
                        <div class="progress-bar {{ $barClass }}" role="progressbar" style="width: {{ $pct }}%">
                            {{ $pct }}%
                        </div>
                    </div>
                    @endif
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ number_format($todayTokens) }}</div>
                                <small class="text-muted">Tokens usados hoy</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 text-primary">{{ $todayRequests }}</div>
                                <small class="text-muted">Peticiones hoy</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="h4 mb-0 {{ $quota['limit'] > 0 ? 'text-primary' : 'text-muted' }}">
                                    {{ $quota['limit'] > 0 ? number_format($quota['limit']) : 'Sin límite' }}
                                </div>
                                <small class="text-muted">Cuota diaria</small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Formulario de API Key propia --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="bi bi-key"></i> Tu propia API Key</h6>
                <p class="text-muted small mb-3">
                    Si configuras tu propia API Key, las llamadas a IA (editor, plugins como News Aggregator, etc.)
                    se facturarán directamente en tu cuenta del proveedor. No consumirás la cuota del sistema y no tendrás límite diario impuesto.
                </p>

                <form action="{{ admin_url('/ai/settings') }}" method="POST">
                    @csrf
                    @php
                        $currentType = $tenantProvider['provider_type'] ?? 'openai';
                        $currentModel = $tenantProvider['model'] ?? 'gpt-4';
                        $currentEndpoint = $tenantProvider['endpoint'] ?? '';
                    @endphp
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="provider_type" class="form-label">Proveedor *</label>
                                <select class="form-select" id="provider_type" name="provider_type" onchange="updateProviderUI()">
                                    <option value="openai" {{ $currentType === 'openai' ? 'selected' : '' }}>OpenAI</option>
                                    <option value="claude" {{ $currentType === 'claude' ? 'selected' : '' }}>Claude (Anthropic)</option>
                                    <option value="gemini" {{ $currentType === 'gemini' ? 'selected' : '' }}>Gemini (Google)</option>
                                    <option value="ollama" {{ $currentType === 'ollama' ? 'selected' : '' }}>Ollama (Local)</option>
                                    <option value="minimax" {{ $currentType === 'minimax' ? 'selected' : '' }}>MiniMax</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key <span id="api_key_required">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="api_key" name="api_key"
                                           value="{{ $tenantProvider['api_key'] ?? '' }}"
                                           placeholder="sk-...">
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleApiKey()">
                                        <i class="bi bi-eye" id="toggle-icon"></i>
                                    </button>
                                </div>
                                <div class="form-text" id="api_key_help">Tu clave de API. Consúltala en el panel de tu proveedor.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="endpoint_row" style="display: {{ $currentType === 'ollama' ? 'flex' : 'none' }};">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="endpoint" class="form-label">Endpoint (URL del servidor)</label>
                                <input type="url" class="form-control" id="endpoint" name="endpoint"
                                       value="{{ $currentEndpoint }}"
                                       placeholder="http://localhost:11434">
                                <div class="form-text">URL de tu servidor Ollama. Si lo ejecutas localmente, suele ser <code>http://localhost:11434</code>.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="model" class="form-label">Modelo</label>
                                <select class="form-select" id="model" name="model">
                                    {{-- Populated by JS based on provider_type --}}
                                </select>
                                <div class="form-text" id="model_help">Selecciona el modelo de IA a utilizar.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_tokens" class="form-label">Max tokens por respuesta</label>
                                <input type="number" class="form-control" id="max_tokens" name="max_tokens"
                                       value="{{ $tenantProvider['max_tokens'] ?? 1500 }}" min="50" max="8000">
                                <div class="form-text">Límite de tokens por cada respuesta individual de la IA.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="temperature" class="form-label">Temperatura</label>
                                <input type="number" class="form-control" id="temperature" name="temperature"
                                       value="{{ $tenantProvider['temperature'] ?? 0.7 }}" min="0" max="2" step="0.1">
                                <div class="form-text">0 = determinista, 1 = creativo, 2 = muy creativo.</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> {{ $tenantProvider ? 'Actualizar configuración' : 'Guardar API Key' }}
                        </button>
                        @if($tenantProvider)
                        <a href="{{ admin_url('/ai/settings/delete-key') }}" class="btn btn-outline-danger"
                           onclick="return confirm('¿Eliminar tu API Key propia? Las llamadas a IA volverán a usar el proveedor del sistema (con cuota).')">
                            <i class="bi bi-trash"></i> Eliminar mi API Key
                        </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function toggleApiKey() {
    const input = document.getElementById('api_key');
    const icon = document.getElementById('toggle-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

const providerModels = {
    openai: [
        { value: 'gpt-4', label: 'GPT-4' },
        { value: 'gpt-4o', label: 'GPT-4o' },
        { value: 'gpt-4o-mini', label: 'GPT-4o Mini' },
        { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
        { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' }
    ],
    claude: [
        { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4' },
        { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet' },
        { value: 'claude-3-5-haiku-20241022', label: 'Claude 3.5 Haiku' },
        { value: 'claude-3-opus-20240229', label: 'Claude 3 Opus' }
    ],
    gemini: [
        { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash' },
        { value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash Lite' },
        { value: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro' },
        { value: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash' }
    ],
    ollama: [
        { value: 'llama3', label: 'Llama 3' },
        { value: 'llama3.1', label: 'Llama 3.1' },
        { value: 'mistral', label: 'Mistral' },
        { value: 'mixtral', label: 'Mixtral' },
        { value: 'gemma2', label: 'Gemma 2' },
        { value: 'qwen2.5', label: 'Qwen 2.5' },
        { value: 'phi3', label: 'Phi 3' },
        { value: 'codellama', label: 'Code Llama' }
    ],
    minimax: [
        { value: 'MiniMax-Text-01', label: 'MiniMax Text 01' },
        { value: 'abab6.5s-chat', label: 'Abab 6.5s Chat' },
        { value: 'abab6.5-chat', label: 'Abab 6.5 Chat' },
        { value: 'abab5.5-chat', label: 'Abab 5.5 Chat' }
    ]
};

const providerHelp = {
    openai: { placeholder: 'sk-...', help: 'Tu clave de API de OpenAI. La puedes obtener en <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>.', required: true },
    claude: { placeholder: 'sk-ant-...', help: 'Tu clave de API de Anthropic. La puedes obtener en <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>.', required: true },
    gemini: { placeholder: 'AIza...', help: 'Tu clave de API de Google AI. La puedes obtener en <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com</a>.', required: true },
    ollama: { placeholder: '(opcional)', help: 'Ollama no requiere API Key por defecto. Solo necesaria si has configurado autenticación en tu servidor.', required: false },
    minimax: { placeholder: 'eyJ...', help: 'Tu clave de API de MiniMax. La puedes obtener en <a href="https://www.minimaxi.com" target="_blank">minimaxi.com</a>.', required: true }
};

const savedModel = @json($currentModel);

function updateProviderUI() {
    const type = document.getElementById('provider_type').value;
    const modelSelect = document.getElementById('model');
    const endpointRow = document.getElementById('endpoint_row');
    const apiKeyInput = document.getElementById('api_key');
    const apiKeyHelp = document.getElementById('api_key_help');
    const apiKeyReq = document.getElementById('api_key_required');

    // Update models
    const models = providerModels[type] || [];
    modelSelect.innerHTML = '';
    models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.value;
        opt.textContent = m.label;
        if (m.value === savedModel) opt.selected = true;
        modelSelect.appendChild(opt);
    });

    // Show/hide endpoint for Ollama
    endpointRow.style.display = (type === 'ollama') ? 'flex' : 'none';

    // Update API key help
    const info = providerHelp[type] || providerHelp.openai;
    apiKeyInput.placeholder = info.placeholder;
    apiKeyHelp.innerHTML = info.help;
    apiKeyReq.style.display = info.required ? 'inline' : 'none';
    apiKeyInput.required = info.required;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateProviderUI);
</script>
@endsection
