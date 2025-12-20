@extends('layouts.app')

@section('title', 'Email Routing - ' . $tenant['name'])

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-envelope-at"></i> Email Routing - {{ $tenant['name'] }}</h2>
                <p class="text-muted mb-0">
                    <i class="bi bi-globe"></i> {{ $tenant['domain'] }}
                </p>
            </div>
            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        {{-- Estado de Email Routing --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Estado del Servicio</h5>
                @if($email_routing_status['enabled'])
                    <span class="badge bg-success">Activo</span>
                @else
                    <span class="badge bg-secondary">Desactivado</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p class="mb-2">
                            <strong>Estado:</strong>
                            @if($email_routing_status['enabled'])
                                <span class="text-success">Email Routing está activo</span>
                            @else
                                <span class="text-muted">Email Routing está desactivado</span>
                            @endif
                        </p>
                        @if(!empty($email_routing_status['created']))
                            <p class="mb-0 text-muted">
                                <small>Activado: {{ date('d/m/Y H:i', strtotime($email_routing_status['created'])) }}</small>
                            </p>
                        @endif
                    </div>
                    <div class="col-md-4 text-end">
                        @if($email_routing_status['enabled'])
                            <button type="button" class="btn btn-danger" onclick="toggleEmailRouting(false)">
                                <i class="bi bi-x-circle"></i> Desactivar Email Routing
                            </button>
                        @else
                            <button type="button" class="btn btn-success" onclick="toggleEmailRouting(true)">
                                <i class="bi bi-check-circle"></i> Activar Email Routing
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($email_routing_status['enabled'])
            {{-- Catch-All Rule --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-envelope-open"></i> Catch-All (Recibir Todos los Emails)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Los emails enviados a cualquier dirección de <strong>{{ $tenant['domain'] }}</strong>
                        que no tenga una regla específica serán redirigidos automáticamente a:
                    </p>

                    <form id="form-catch-all" onsubmit="return updateCatchAll(event)">
                        {!! csrf_field() !!}
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label for="catch_all_destination" class="form-label">Email Destino</label>
                                <input type="email" class="form-control" id="catch_all_destination"
                                       name="destination_email" required
                                       value="{{ $catch_all_rule['actions'][0]['value'][0] ?? '' }}"
                                       placeholder="destino@gmail.com">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="catch_all_enabled"
                                           name="enabled" value="1"
                                           {{ ($catch_all_rule['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="catch_all_enabled">
                                        Activo
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-save"></i> Actualizar Catch-All
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Reglas de Forwarding Específicas --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-arrow-right-square"></i> Reglas de Forwarding</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateRule">
                        <i class="bi bi-plus-circle"></i> Nueva Regla
                    </button>
                </div>
                <div class="card-body">
                    @if(empty($routing_rules))
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i>
                            No hay reglas específicas configuradas. Todos los emails se redirigen según el Catch-All.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>De (Email en {{ $tenant['domain'] }})</th>
                                        <th>Para (Email Destino)</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="rules-table-body">
                                    @foreach($routing_rules as $rule)
                                        @php
                                            // Extraer información de la regla
                                            $fromEmail = $rule['matchers'][0]['value'] ?? 'N/A';
                                            $toEmail = $rule['actions'][0]['value'][0] ?? 'N/A';
                                            $enabled = $rule['enabled'] ?? false;
                                            $ruleId = $rule['id'];
                                        @endphp
                                        <tr id="rule-{{ $ruleId }}">
                                            <td>
                                                <strong>{{ $fromEmail }}</strong>
                                            </td>
                                            <td>
                                                <i class="bi bi-arrow-right text-muted"></i> {{ $toEmail }}
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox"
                                                           id="rule-toggle-{{ $ruleId }}"
                                                           {{ $enabled ? 'checked' : '' }}
                                                           onchange="toggleRuleStatus('{{ $ruleId }}', this.checked)">
                                                    <label class="form-check-label" for="rule-toggle-{{ $ruleId }}">
                                                        <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ $enabled ? 'Activo' : 'Inactivo' }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="deleteRule('{{ $ruleId }}', '{{ $fromEmail }}')">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Email Routing desactivado</strong><br>
                Activa el servicio para poder configurar reglas de forwarding.
            </div>
        @endif

    </div>
</div>

{{-- Modal: Crear Nueva Regla --}}
<div class="modal fade" id="modalCreateRule" tabindex="-1" aria-labelledby="modalCreateRuleLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-create-rule" onsubmit="return createRule(event)">
                {!! csrf_field() !!}
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateRuleLabel">
                        <i class="bi bi-plus-circle"></i> Nueva Regla de Forwarding
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="from_email" class="form-label">De (Email en tu dominio)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="from_email" name="from_email"
                                   placeholder="info" required>
                            <span class="input-group-text">@{{ $tenant['domain'] }}</span>
                        </div>
                        <div class="form-text">
                            Los emails enviados a esta dirección serán redirigidos
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="to_email" class="form-label">Para (Email Destino)</label>
                        <input type="email" class="form-control" id="to_email" name="to_email"
                               placeholder="destino@gmail.com" required>
                        <div class="form-text">
                            Email donde se recibirán los mensajes
                        </div>
                    </div>

                    <div class="alert alert-info alert-sm mb-0">
                        <small>
                            <i class="bi bi-info-circle"></i>
                            <strong>Ejemplo:</strong> Si configuras <code>info@{{ $tenant['domain'] }}</code> → <code>tu@gmail.com</code>,
                            todos los emails enviados a info@{{ $tenant['domain'] }} llegarán a tu@gmail.com
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Crear Regla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const tenantId = {{ $tenant['id'] }};
const csrfToken = '{{ $csrf_token }}';

// Activar/Desactivar Email Routing
function toggleEmailRouting(enable) {
    const action = enable ? 'enable' : 'disable';
    const url = `/musedock/domain-manager/${tenantId}/email-routing/${action}`;

    if (!enable) {
        if (!confirm('¿Estás seguro de desactivar Email Routing? Se deshabilitarán todas las reglas.')) {
            return;
        }
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ _csrf_token: csrfToken })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}

// Actualizar Catch-All
function updateCatchAll(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const url = `/musedock/domain-manager/${tenantId}/email-routing/catch-all`;

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success');
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Error de conexión', 'error');
    });

    return false;
}

// Crear nueva regla
function createRule(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const url = `/musedock/domain-manager/${tenantId}/email-routing/rules`;

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Error de conexión', 'error');
    });

    return false;
}

// Toggle estado de regla
function toggleRuleStatus(ruleId, enabled) {
    const url = `/musedock/domain-manager/${tenantId}/email-routing/rules/${ruleId}/toggle`;

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            _csrf_token: csrfToken,
            enabled: enabled ? '1' : '0'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar badge
            const row = document.getElementById(`rule-${ruleId}`);
            const badge = row.querySelector('.badge');
            badge.className = enabled ? 'badge bg-success' : 'badge bg-secondary';
            badge.textContent = enabled ? 'Activo' : 'Inactivo';

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: data.message,
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
            // Revertir toggle
            document.getElementById(`rule-toggle-${ruleId}`).checked = !enabled;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error', 'Error de conexión', 'error');
        document.getElementById(`rule-toggle-${ruleId}`).checked = !enabled;
    });
}

// Eliminar regla
function deleteRule(ruleId, fromEmail) {
    Swal.fire({
        title: '¿Eliminar regla?',
        html: `¿Estás seguro de eliminar la regla para <strong>${fromEmail}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const url = `/musedock/domain-manager/${tenantId}/email-routing/rules/${ruleId}/delete`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ _csrf_token: csrfToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Eliminar fila de la tabla
                    document.getElementById(`rule-${ruleId}`).remove();

                    Swal.fire('¡Eliminada!', data.message, 'success');
                } else {
                    Swal.fire('Error', data.error || 'Error desconocido', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
}
</script>
@endpush

@endsection
