@extends('Customer.layout')

@section('styles')
<style>
    .domain-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 25px;
    }
    .domain-header h2 {
        margin: 0 0 5px 0;
        font-size: 1.5rem;
    }
    .domain-header .domain-name {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    .section-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
    }
    .section-card .card-header {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        padding: 15px 20px;
        border-radius: 12px 12px 0 0;
    }
    .section-card .card-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #374151;
    }
    .section-card .card-body {
        padding: 20px;
    }
    .dns-record {
        background: #f9fafb;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 10px;
        border: 1px solid #e5e7eb;
    }
    .dns-record .type {
        font-weight: 600;
        color: #667eea;
        min-width: 60px;
        display: inline-block;
    }
    .dns-record .name {
        color: #374151;
    }
    .dns-record .content {
        color: #6b7280;
        font-size: 0.9rem;
        word-break: break-all;
    }
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #f3f4f6; color: #6b7280; }
    .email-rule {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 15px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 10px;
        border: 1px solid #e5e7eb;
    }
    .email-rule .from {
        font-weight: 500;
        color: #374151;
    }
    .email-rule .to {
        color: #6b7280;
    }
    .email-rule .arrow {
        color: #9ca3af;
        margin: 0 10px;
    }
    .btn-action {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
    }
</style>
@endsection

@section('content')
<div class="domain-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="bi bi-globe me-2"></i>Gestionar Dominio</h2>
            <div class="domain-name"><?= htmlspecialchars($tenant['domain']) ?></div>
        </div>
        <a href="/customer/dashboard" class="btn btn-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<!-- Estado del Dominio -->
<div class="section-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-info-circle me-2"></i>Estado del Dominio</h5>
        <?php
        $statusClass = $tenant['status'] === 'active' ? 'status-active' : 'status-inactive';
        $statusLabel = [
            'active' => 'Activo',
            'pending' => 'Pendiente',
            'waiting_ns_change' => 'Esperando DNS',
            'error' => 'Error'
        ][$tenant['status']] ?? ucfirst($tenant['status']);
        ?>
        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
    </div>
    <div class="card-body">
        <?php if ($tenant['status'] === 'waiting_ns_change'): ?>
        <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Accion requerida:</strong> Cambia los nameservers de tu dominio a:
            <?php $nameservers = json_decode($tenant['cloudflare_nameservers'] ?? '[]', true); ?>
            <?php if (!empty($nameservers)): ?>
            <div class="mt-2 bg-light p-2 rounded">
                <?php foreach ($nameservers as $i => $ns): ?>
                <code class="d-block">NS<?= $i + 1 ?>: <?= htmlspecialchars($ns) ?></code>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <p class="mb-0 text-muted">
            <i class="bi bi-check-circle text-success me-2"></i>
            Tu dominio esta configurado y funcionando correctamente.
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- Registros DNS -->
<div class="section-card">
    <div class="card-header">
        <h5><i class="bi bi-diagram-3 me-2"></i>Registros DNS</h5>
    </div>
    <div class="card-body">
        <?php if (empty($dns_records)): ?>
        <p class="text-muted mb-0">No hay registros DNS disponibles.</p>
        <?php else: ?>
        <?php foreach ($dns_records as $record): ?>
        <div class="dns-record">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="type"><?= htmlspecialchars($record['type']) ?></span>
                    <span class="name"><?= htmlspecialchars($record['name']) ?></span>
                </div>
                <?php if (!empty($record['proxied'])): ?>
                <span class="badge bg-warning text-dark">
                    <i class="bi bi-shield-check"></i> Proxied
                </span>
                <?php endif; ?>
            </div>
            <div class="content mt-1">
                <i class="bi bi-arrow-right me-1"></i>
                <?= htmlspecialchars($record['content']) ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Email Routing -->
<div class="section-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-envelope-at me-2"></i>Email Routing</h5>
        <?php if ($email_routing_status['enabled']): ?>
        <span class="status-badge status-active"><i class="bi bi-check-circle me-1"></i>Activo</span>
        <?php else: ?>
        <span class="status-badge status-inactive">Desactivado</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Redirige correos enviados a direcciones de <strong><?= htmlspecialchars($tenant['domain']) ?></strong> a tu email personal.
        </p>

        <?php if (!$email_routing_status['enabled']): ?>
        <button type="button" class="btn btn-primary btn-action" onclick="toggleEmailRouting(true)">
            <i class="bi bi-power me-1"></i> Activar Email Routing
        </button>
        <?php else: ?>

        <!-- Catch-All -->
        <div class="mb-4 p-3 bg-light rounded">
            <h6 class="mb-2"><i class="bi bi-envelope-open me-1"></i> Catch-All (Recibir todos los emails)</h6>
            <p class="text-muted small mb-2">
                Los emails a cualquier direccion de <?= htmlspecialchars($tenant['domain']) ?> se redirigen a:
            </p>
            <form id="catchAllForm" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <input type="email" class="form-control" name="destination_email"
                           value="<?= htmlspecialchars($catch_all_rule['actions'][0]['value'][0] ?? '') ?>"
                           placeholder="tu@email.com">
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1"
                               <?= ($catch_all_rule['enabled'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label">Activo</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>

        <!-- Reglas Especificas -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-arrow-right-square me-1"></i> Reglas Especificas</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showCreateRuleModal()">
                    <i class="bi bi-plus-circle me-1"></i> Nueva Regla
                </button>
            </div>

            <?php if (empty($routing_rules)): ?>
            <p class="text-muted small">No hay reglas especificas. Todos los emails usan el catch-all.</p>
            <?php else: ?>
            <?php foreach ($routing_rules as $rule): ?>
            <?php
                $fromEmail = $rule['matchers'][0]['value'] ?? 'N/A';
                $toEmail = $rule['actions'][0]['value'][0] ?? 'N/A';
                $ruleId = $rule['id'];
                $enabled = $rule['enabled'] ?? false;
            ?>
            <div class="email-rule" id="rule-<?= $ruleId ?>">
                <div>
                    <span class="from"><?= htmlspecialchars($fromEmail) ?></span>
                    <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                    <span class="to"><?= htmlspecialchars($toEmail) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $enabled ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $enabled ? 'Activo' : 'Inactivo' ?>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="deleteRule('<?= $ruleId ?>', '<?= htmlspecialchars($fromEmail) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <hr>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="toggleEmailRouting(false)">
            <i class="bi bi-x-circle me-1"></i> Desactivar Email Routing
        </button>
        <?php endif; ?>
    </div>
</div>
@endsection

@section('scripts')
<script>
const tenantId = <?= $tenant['id'] ?>;
const tenantDomain = '<?= $tenant['domain'] ?>';
const csrfToken = '<?= $csrf_token ?>';

// Toggle Email Routing
function toggleEmailRouting(enable) {
    const action = enable ? 'enable' : 'disable';

    if (!enable) {
        Swal.fire({
            title: 'Desactivar Email Routing?',
            text: 'Se deshabilitaran todas las reglas de correo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Si, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                executeToggle(action);
            }
        });
    } else {
        executeToggle(action);
    }
}

function executeToggle(action) {
    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);

    fetch(`/customer/domain/${tenantId}/email-routing/${action}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Exito!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Error de conexion', 'error');
    });
}

// Catch-All Form
document.getElementById('catchAllForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('_csrf_token', csrfToken);

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch(`/customer/domain/${tenantId}/email-routing/catch-all`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Guardado!', text: data.message, timer: 1500, showConfirmButton: false });
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Error de conexion', 'error');
    });
});

// Crear nueva regla
function showCreateRuleModal() {
    Swal.fire({
        title: '<i class="bi bi-plus-circle text-primary"></i> Nueva Regla',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">De (Email en tu dominio)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="swal-from-email" placeholder="info">
                        <span class="input-group-text">@${tenantDomain}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Para (Email Destino)</label>
                    <input type="email" class="form-control" id="swal-to-email" placeholder="destino@gmail.com">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> Crear',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#667eea',
        width: '450px',
        didOpen: () => document.getElementById('swal-from-email').focus(),
        preConfirm: () => {
            const fromEmail = document.getElementById('swal-from-email').value.trim();
            const toEmail = document.getElementById('swal-to-email').value.trim();

            if (!fromEmail || !toEmail) {
                Swal.showValidationMessage('Todos los campos son obligatorios');
                return false;
            }
            return { fromEmail, toEmail };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            createRule(result.value.fromEmail, result.value.toEmail);
        }
    });
}

function createRule(fromEmail, toEmail) {
    Swal.fire({ title: 'Creando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('from_email', fromEmail);
    formData.append('to_email', toEmail);

    fetch(`/customer/domain/${tenantId}/email-routing/rules`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Exito!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'Error desconocido', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Error de conexion', 'error');
    });
}

// Eliminar regla
function deleteRule(ruleId, fromEmail) {
    Swal.fire({
        title: 'Eliminar regla?',
        html: `Eliminar la regla para <strong>${fromEmail}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            fetch(`/customer/domain/${tenantId}/email-routing/rules/${ruleId}/delete`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`rule-${ruleId}`).remove();
                    Swal.fire({ icon: 'success', title: 'Eliminada!', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire('Error', data.error || 'Error desconocido', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Error de conexion', 'error');
            });
        }
    });
}
</script>
@endsection
