@extends('layouts.app')

@section('title', 'Configuración de AI Writer')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Configuración del Sistema de IA</h1>

    {{-- Flash messages handled by layout SweetAlert2 --}}

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ajustes Globales</h6>
            <small>Configuración por defecto del sistema de IA. Se aplica a todos los tenants que no tengan una cuota específica asignada en la tabla de abajo.</small>
        </div>
        <div class="card-body">
            <form action="/musedock/ai/settings/update" method="POST">
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
                    <label for="daily_token_limit" class="form-label fw-bold">Límite Diario de Tokens por Defecto</label>
                    <input type="number" class="form-control" id="daily_token_limit" name="daily_token_limit"
                           value="{{ isset($settings['ai_daily_token_limit']) ? $settings['ai_daily_token_limit'] : '0' }}" min="0">
                    <div class="form-text">Máximo de tokens que un tenant puede consumir al día sumando todas sus llamadas a IA (editor, plugins, cron...). Valor <strong>0 = sin límite</strong>. Si necesitas un límite diferente para un tenant concreto, configúralo en la tabla "Cuotas por Tenant" de abajo.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="log_all_prompts" name="log_all_prompts" value="1"
                               {{ isset($settings['ai_log_all_prompts']) && $settings['ai_log_all_prompts'] == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="log_all_prompts">Guardar Prompts Completos Globalmente</label>
                    </div>
                    <div class="form-text">Si está activo, se guardan los prompts completos enviados a la IA en los logs de uso. Si está desactivado, solo se guardan los primeros 100 caracteres (útil para reducir espacio en disco).</div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Guardar Configuración Global</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Cuotas por Tenant --}}
    @if(!empty($tenants))
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cuotas Diarias por Tenant</h6>
            <small>Aquí puedes asignar un límite de tokens diario diferente a cada tenant. Los tenants sin cuota específica usarán el límite global de arriba (actualmente: <strong>{{ $settings['ai_daily_token_limit'] == '0' ? 'sin límite' : number_format($settings['ai_daily_token_limit']) . ' tokens/día' }}</strong>).</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tenant</th>
                            <th>Dominio</th>
                            <th>Cuota diaria</th>
                            <th style="width: 220px;">Nuevo límite</th>
                            <th style="width: 100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $tenant)
                        <tr>
                            <td><small class="text-muted">{{ $tenant['id'] }}</small></td>
                            <td><strong>{{ $tenant['name'] }}</strong></td>
                            <td><small>{{ $tenant['domain'] ?? '-' }}</small></td>
                            <td>
                                @if(isset($tenantQuotas[$tenant['id']]) && $tenantQuotas[$tenant['id']] !== null)
                                    <span class="badge bg-primary">{{ number_format($tenantQuotas[$tenant['id']]) }} tokens/día</span>
                                @else
                                    <span class="badge bg-secondary">Global</span>
                                @endif
                            </td>
                            <td>
                                <form action="/musedock/ai/settings/tenant-quota" method="POST" class="d-flex gap-1">
                                    @csrf
                                    <input type="hidden" name="tenant_id" value="{{ $tenant['id'] }}">
                                    <input type="number" class="form-control form-control-sm" name="token_limit"
                                           value="{{ $tenantQuotas[$tenant['id']] ?? '' }}" min="0"
                                           placeholder="0 = global">
                            </td>
                            <td>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">
                <strong>0 o vacío</strong> = el tenant usa el límite global configurado arriba.
                Un valor específico reemplaza el global solo para ese tenant.
                El límite cuenta <strong>todas</strong> las llamadas a IA del tenant: editor de texto, API, plugins (news-aggregator, etc.) y tareas automáticas por cron.
                Cuando un tenant alcanza su límite diario, todas sus peticiones de IA se bloquean hasta las 00:00.
            </div>
        </div>
    </div>
    @endif
</div>
@endsection