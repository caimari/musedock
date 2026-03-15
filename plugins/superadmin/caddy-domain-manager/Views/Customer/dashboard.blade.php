@extends('Customer.layout')

@section('styles')
<style>
    .dashboard-header {
        margin-bottom: 30px;
    }
    .dashboard-header h2 {
        font-size: 1.75rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 5px;
    }
    .dashboard-header p {
        color: #6b7280;
        font-size: 0.95rem;
        margin: 0;
    }

    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
        height: 100%;
    }
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #667eea;
    }
    .stats-card .icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    .stats-card .number {
        font-size: 1.875rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
        line-height: 1;
    }
    .stats-card .label {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin: 30px 0 20px 0;
    }

    .tenant-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
        border: 1px solid #e5e7eb;
    }
    .tenant-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #d1d5db;
        transform: translateY(-1px);
    }
    .tenant-card .domain {
        font-size: 1rem;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tenant-card .domain i {
        font-size: 1.1rem;
    }
    .tenant-card .domain a {
        color: #667eea;
        text-decoration: none;
    }
    .tenant-card .domain a:hover {
        text-decoration: underline;
    }
    .tenant-card .info {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .tenant-card .info .badge {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 500;
    }
    .badge-plan-free {
        background: #10b981;
        color: white;
    }
    .badge-plan-custom {
        background: #667eea;
        color: white;
    }
    .badge-status-active {
        background: #10b981;
        color: white;
    }
    .badge-status-error {
        background: #ef4444;
        color: white;
    }
    .badge-status-waiting_ns_change {
        background: #f59e0b;
        color: white;
    }
    .tenant-card .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .tenant-card .actions .btn {
        font-size: 0.875rem;
        padding: 6px 14px;
        border-radius: 6px;
    }
    .action-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 30px;
        flex-wrap: wrap;
    }
    .action-buttons .btn {
        font-size: 0.95rem;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 500;
    }
</style>
@endsection

@section('content')
<div class="dashboard-header">
    <h2>Dashboard</h2>
    <p>Bienvenido a tu panel de control</p>
</div>

<!-- Estadisticas -->
<div class="row">
    <div class="col-md-4">
        <div class="stats-card">
            <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <i class="bi bi-globe"></i>
            </div>
            <div class="number"><?= $stats['total_tenants'] ?? 0 ?></div>
            <div class="label">Total de sitios</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <div class="icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="number"><?= $stats['active_tenants'] ?? 0 ?></div>
            <div class="label">Sitios activos</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card">
            <div class="icon" style="background: linear-gradient(135deg, #ffc107, #ff9800); color: white;">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="number"><?= $stats['cloudflare_protected'] ?? 0 ?></div>
            <div class="label">Protegidos con Cloudflare</div>
        </div>
    </div>
</div>

<!-- Lista de tenants -->
<h4 class="section-title">Mis Sitios</h4>

<?php if (empty($tenants)): ?>
<div class="alert alert-info" style="border-radius: 10px; border: 1px solid #3b82f6; background: #eff6ff;">
    <i class="bi bi-info-circle me-2"></i>
    Aún no tienes sitios web. Solicita tu primer subdominio FREE o incorpora tu dominio personalizado!
</div>
<?php else: ?>
    <?php foreach ($tenants as $tenant): ?>
    <div class="tenant-card">
        <div class="domain">
            <i class="bi bi-globe"></i>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>" target="_blank">
                <?= htmlspecialchars($tenant['domain']) ?>
            </a>
        </div>

        <div class="info">
            <span class="badge badge-plan-<?= strtolower($tenant['plan']) ?>">
                Plan <?= strtoupper($tenant['plan']) ?>
            </span>

            <span class="badge badge-status-<?= $tenant['status'] ?>">
                <?php
                $statusLabels = [
                    'active' => 'Activo',
                    'pending' => 'Pendiente',
                    'waiting_ns_change' => 'Esperando DNS',
                    'error' => 'Error',
                    'suspended' => 'Suspendido'
                ];
                echo $statusLabels[$tenant['status']] ?? ucfirst($tenant['status']);
                ?>
            </span>

            <!-- Health Check Status -->
            <?php if (isset($tenant['health_badge'])): ?>
            <span class="badge <?= $tenant['health_status'] === 'healthy' ? 'bg-success' : ($tenant['health_status'] === 'degraded' ? 'bg-warning' : 'bg-danger') ?>"
                  title="<?= htmlspecialchars(implode(', ', $tenant['health_check']['errors'] ?? [])) ?>">
                <?= $tenant['health_badge'] ?>
            </span>
            <?php endif; ?>

            <?php if (!empty($tenant['cloudflare_proxied'])): ?>
            <span class="badge bg-warning text-dark">
                <i class="bi bi-shield-fill-check"></i> Cloudflare
            </span>
            <?php endif; ?>

            <?php if (!empty($tenant['is_subdomain'])): ?>
            <span class="badge bg-info">
                <i class="bi bi-link-45deg"></i> Subdominio
            </span>
            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if ($tenant['status'] === 'active'): ?>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
               class="btn btn-sm btn-primary" target="_blank">
                <i class="bi bi-gear"></i> Admin
            </a>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>"
               class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="bi bi-eye"></i> Ver Sitio
            </a>
            <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
            <a href="/customer/tenant/<?= $tenant['id'] ?>/dns-email" class="btn btn-sm btn-outline-info">
                <i class="bi bi-envelope-at"></i> DNS / Email
            </a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-heart-pulse"></i> Verificar
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="toggleAliasPanel(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-link-45deg"></i> Alias
            </button>
            <?php elseif ($tenant['status'] === 'waiting_ns_change'): ?>
            <span class="text-warning small">
                <i class="bi bi-hourglass-split"></i> Esperando cambio de nameservers...
            </span>
            <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
            <a href="/customer/tenant/<?= $tenant['id'] ?>/dns-email" class="btn btn-sm btn-outline-info">
                <i class="bi bi-envelope-at"></i> Gestionar
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Boton de Retry/Verificar si hay problemas -->
            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
                <?php
                $isCustomDomain = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']);
                $isWaitingNS = ($tenant['status'] ?? '') === 'waiting_ns_change';
                ?>
                <?php if ($isCustomDomain && $isWaitingNS): ?>
                <button class="btn btn-sm btn-info" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-check2-circle"></i> Verificar NS
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                    <i class="bi bi-arrow-clockwise"></i> Reintentar
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Panel de Aliases (oculto por defecto) -->
        <div class="alias-panel d-none" id="alias-panel-<?= $tenant['id'] ?>" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong style="font-size: 0.9rem;"><i class="bi bi-link-45deg"></i> Alias de Dominio</strong>
                <span class="badge bg-secondary" id="alias-count-<?= $tenant['id'] ?>">...</span>
            </div>

            <div id="alias-list-<?= $tenant['id'] ?>" style="margin-bottom: 10px;">
                <small class="text-muted">Cargando...</small>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <input type="text" class="form-control form-control-sm" id="alias-input-<?= $tenant['id'] ?>"
                       placeholder="midominio.com" style="max-width: 220px; font-size: 0.85rem;"
                       onkeypress="if(event.key==='Enter'){event.preventDefault();submitAlias(<?= $tenant['id'] ?>)}">
                <div class="form-check form-check-inline" style="margin-bottom: 0;">
                    <input type="checkbox" class="form-check-input" id="alias-www-<?= $tenant['id'] ?>" checked style="margin-top: 3px;">
                    <label class="form-check-label" style="font-size: 0.8rem;" for="alias-www-<?= $tenant['id'] ?>">www</label>
                </div>
                <div class="form-check form-check-inline" style="margin-bottom: 0;">
                    <input type="checkbox" class="form-check-input" id="alias-skipcf-<?= $tenant['id'] ?>" style="margin-top: 3px;">
                    <label class="form-check-label" style="font-size: 0.8rem;" for="alias-skipcf-<?= $tenant['id'] ?>">Sin Cloudflare</label>
                </div>
                <button class="btn btn-sm btn-primary" onclick="submitAlias(<?= $tenant['id'] ?>)">
                    <i class="bi bi-plus-lg"></i> Añadir
                </button>
            </div>
            <div style="margin-top: 6px;">
                <small class="text-muted"><i class="bi bi-info-circle"></i> Marca "Sin Cloudflare" si el dominio ya apunta a nuestro servidor.</small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Dominios Registrados -->
<h4 class="section-title">Mis Dominios Registrados</h4>

<?php if (empty($domainOrders)): ?>
<div class="alert alert-info" style="border-radius: 10px; border: 1px solid #3b82f6; background: #eff6ff;">
    <i class="bi bi-info-circle me-2"></i>
    Aún no tienes dominios registrados. Puedes registrar uno nuevo o transferir un dominio existente.
</div>
<?php else: ?>
    <?php foreach ($domainOrders as $order): ?>
    <?php
        $fullDomain = $order['full_domain'] ?? (($order['domain'] ?? '') . '.' . ($order['extension'] ?? ''));
        $orderStatus = $order['status'] ?? 'unknown';
        $statusClassMap = [
            'active' => 'bg-success',
            'registered' => 'bg-success',
            'pending' => 'bg-warning',
            'processing' => 'bg-warning',
            'in_progress' => 'bg-warning',
            'failed' => 'bg-danger',
            'error' => 'bg-danger',
        ];
        $statusLabelMap = [
            'active' => 'Activo',
            'registered' => 'Registrado',
            'pending' => 'Pendiente',
            'processing' => 'En Proceso',
            'in_progress' => 'En Proceso',
            'failed' => 'Error',
            'error' => 'Error',
        ];
        $statusBadgeClass = $statusClassMap[$orderStatus] ?? 'bg-secondary';
        $statusText = $statusLabelMap[$orderStatus] ?? ucfirst((string)$orderStatus);
        $hostingType = $order['hosting_type'] ?? 'musedock_hosting';
    ?>
    <div class="tenant-card">
        <div class="domain">
            <i class="bi bi-globe"></i>
            <?= htmlspecialchars($fullDomain) ?>
        </div>

        <div class="info">
            <span class="badge <?= htmlspecialchars($statusBadgeClass) ?>">
                <?= htmlspecialchars($statusText) ?>
            </span>

            <?php if ($hostingType === 'musedock_hosting'): ?>
            <span class="badge bg-info">
                <i class="bi bi-hdd-stack"></i> DNS + Hosting MuseDock
            </span>
            <?php else: ?>
            <span class="badge bg-secondary">
                <i class="bi bi-globe"></i> Solo Gestion DNS
            </span>
            <?php endif; ?>

            <?php if (!empty($order['cloudflare_zone_id'])): ?>
            <span class="badge bg-warning text-dark">
                <i class="bi bi-shield-fill-check"></i> Cloudflare
            </span>
            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if (in_array($orderStatus, ['active', 'registered'], true)): ?>
            <a href="/customer/domain/<?= (int)$order['id'] ?>/manage" class="btn btn-sm btn-success">
                <i class="bi bi-gear-fill"></i> Administrar
            </a>
            <a href="/customer/domain/<?= (int)$order['id'] ?>/dns" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-hdd-network"></i> Gestionar DNS
            </a>
            <a href="/customer/domain/<?= (int)$order['id'] ?>/contacts" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-person-lines-fill"></i> Contactos
            </a>
            <?php if ($hostingType === 'musedock_hosting' && !empty($order['tenant_domain'])): ?>
            <a href="https://<?= htmlspecialchars($order['tenant_domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
               class="btn btn-sm btn-primary" target="_blank">
                <i class="bi bi-gear"></i> Admin
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Transferencias en Proceso -->
<?php
    $hasPendingTransfers = false;
    if (!empty($domainTransfers)) {
        foreach ($domainTransfers as $transferTmp) {
            if (($transferTmp['status'] ?? '') !== 'completed') {
                $hasPendingTransfers = true;
                break;
            }
        }
    }
?>
<?php if (!empty($domainTransfers) && $hasPendingTransfers): ?>
<h4 class="section-title">Transferencias en Proceso</h4>

<?php foreach ($domainTransfers as $transfer): ?>
<?php if (($transfer['status'] ?? '') !== 'completed'): ?>
<?php
    $transferStatus = $transfer['status'] ?? 'unknown';
    $transferStatusClassMap = [
        'completed' => 'bg-success',
        'pending' => 'bg-warning',
        'processing' => 'bg-warning',
        'in_progress' => 'bg-warning',
        'ACT' => 'bg-info',
        'failed' => 'bg-danger',
        'FAI' => 'bg-danger',
    ];
    $transferStatusLabelMap = [
        'completed' => 'Completada',
        'pending' => 'Pendiente',
        'processing' => 'En Proceso',
        'in_progress' => 'En Proceso',
        'ACT' => 'Lista para Configurar',
        'failed' => 'Fallida',
        'FAI' => 'Fallida',
    ];
    $transferBadgeClass = $transferStatusClassMap[$transferStatus] ?? 'bg-secondary';
    $transferStatusText = $transferStatusLabelMap[$transferStatus] ?? ucfirst((string)$transferStatus);
?>
<div class="tenant-card">
    <div class="domain">
        <i class="bi bi-arrow-left-right"></i>
        <?= htmlspecialchars($transfer['domain'] ?? '') ?>
    </div>

    <div class="info">
        <span class="badge <?= htmlspecialchars($transferBadgeClass) ?>"><?= htmlspecialchars($transferStatusText) ?></span>
        <?php if (!empty($transfer['created_at'])): ?>
        <span class="badge bg-light text-dark">
            <i class="bi bi-calendar"></i> <?= htmlspecialchars(date('d/m/Y', strtotime($transfer['created_at']))) ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a href="/customer/transfer-domain/<?= (int)$transfer['id'] ?>/status" class="btn btn-sm btn-outline-info">
            <i class="bi bi-eye"></i> Ver Estado
        </a>
        <?php if ($transferStatus === 'ACT'): ?>
        <a href="/customer/transfer-domain/<?= (int)$transfer['id'] ?>/status" class="btn btn-sm btn-success">
            <i class="bi bi-check-circle"></i> Completar Configuracion
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<!-- Botones de accion -->
<div class="action-buttons">
    <a href="/customer/request-free-subdomain" class="btn btn-success">
        <i class="bi bi-gift me-2"></i>Solicitar Subdominio FREE
    </a>
    <a href="/customer/request-custom-domain" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Incorporar Dominio Existente
    </a>
    <a href="/customer/register-domain" class="btn btn-info">
        <i class="bi bi-bag-plus me-2"></i>Registrar Nuevo Dominio
    </a>
</div>
@endsection

@section('scripts')
<script>
function retryProvisioning(tenantId, domain) {
    Swal.fire({
        title: 'Verificar configuracion?',
        html: `Deseas verificar la configuracion de <strong>${domain}</strong>?<br><small class="text-muted">Para dominios personalizados, verificaremos si los nameservers han sido cambiados.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, verificar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                html: 'Verificando configuracion...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/customer/tenant/${tenantId}/retry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `_csrf_token=${encodeURIComponent('<?= csrf_token() ?>')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Verificar si es un dominio con NS pendiente
                    if (data.status === 'waiting_ns_change' && data.nameservers) {
                        let nsHtml = '<div class="text-start mt-3">';
                        nsHtml += '<p>' + data.message + '</p>';
                        nsHtml += '<p><strong>Nameservers requeridos:</strong></p>';
                        nsHtml += '<div class="bg-light p-3 rounded">';
                        data.nameservers.forEach((ns, i) => {
                            nsHtml += `<p class="mb-1"><code>NS${i+1}: ${ns}</code></p>`;
                        });
                        nsHtml += '</div>';
                        nsHtml += '<p class="mt-3 text-muted small"><i class="bi bi-info-circle me-1"></i>Los cambios de DNS pueden tardar hasta 48 horas en propagarse.</p>';
                        nsHtml += '</div>';

                        Swal.fire({
                            icon: 'info',
                            title: 'Esperando cambio de DNS',
                            html: nsHtml,
                            confirmButtonColor: '#667eea',
                            confirmButtonText: 'Entendido'
                        });
                    } else if (data.status === 'active') {
                        // Dominio activado
                        Swal.fire({
                            icon: 'success',
                            title: 'Dominio Activado!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        // Respuesta generica de exito
                        Swal.fire({
                            icon: 'success',
                            title: 'Exito!',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al verificar la configuracion',
                        confirmButtonColor: '#667eea'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de red. Por favor intenta de nuevo.',
                    confirmButtonColor: '#667eea'
                });
            });
        }
    });
}

function runHealthCheck(tenantId, domain) {
    Swal.fire({
        title: 'Verificando estado...',
        html: `Ejecutando health check de <strong>${domain}</strong>...`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`/customer/tenant/${tenantId}/health-check`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const healthCheck = data.health_check;
                let htmlContent = '<div class="text-start">';

                // DNS
                htmlContent += `<p class="mb-2"><strong>DNS:</strong> ${healthCheck.checks.dns.passed ? '✅' : '❌'} ${healthCheck.checks.dns.message}</p>`;

                // HTTP
                htmlContent += `<p class="mb-2"><strong>HTTP:</strong> ${healthCheck.checks.http.passed ? '✅' : '❌'} ${healthCheck.checks.http.message}</p>`;

                // SSL
                htmlContent += `<p class="mb-2"><strong>SSL:</strong> ${healthCheck.checks.ssl.passed ? '✅' : '❌'} ${healthCheck.checks.ssl.message}</p>`;

                // Cloudflare
                if (healthCheck.checks.cloudflare) {
                    htmlContent += `<p class="mb-0"><strong>Cloudflare:</strong> ${healthCheck.checks.cloudflare.passed ? '✅' : '❌'} ${healthCheck.checks.cloudflare.message}</p>`;
                }

                htmlContent += '</div>';

                Swal.fire({
                    icon: healthCheck.overall_status === 'healthy' ? 'success' : (healthCheck.overall_status === 'degraded' ? 'warning' : 'error'),
                    title: 'Estado del Sitio',
                    html: htmlContent,
                    confirmButtonColor: '#667eea'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'Error al verificar el estado',
                    confirmButtonColor: '#667eea'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de red. Por favor intenta de nuevo.',
                confirmButtonColor: '#667eea'
            });
        });
}

// ============================================
// Domain Alias Management
// ============================================
const csrfToken = '<?= csrf_token() ?>';

function toggleAliasPanel(tenantId, domain) {
    const panel = document.getElementById('alias-panel-' + tenantId);
    if (panel.classList.contains('d-none')) {
        panel.classList.remove('d-none');
        loadAliases(tenantId);
    } else {
        panel.classList.add('d-none');
    }
}

function loadAliases(tenantId) {
    const listEl = document.getElementById('alias-list-' + tenantId);
    const countEl = document.getElementById('alias-count-' + tenantId);

    fetch(`/customer/tenant/${tenantId}/aliases`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                listEl.innerHTML = '<small class="text-danger">Error cargando aliases.</small>';
                countEl.textContent = '0';
                return;
            }

            const aliases = data.aliases || [];
            countEl.textContent = aliases.length;

            if (aliases.length === 0) {
                listEl.innerHTML = '<small class="text-muted">No hay alias configurados. Añade un dominio propio para que apunte a este sitio.</small>';
                return;
            }

            let html = '';
            aliases.forEach(a => {
                const statusBadge = a.status === 'active'
                    ? '<span class="badge bg-success" style="font-size:0.7rem">activo</span>'
                    : a.status === 'error'
                    ? '<span class="badge bg-danger" style="font-size:0.7rem" title="' + (a.error_log || '') + '">error</span>'
                    : '<span class="badge bg-warning text-dark" style="font-size:0.7rem">' + a.status + '</span>';

                const cfBadge = (!a.cloudflare_zone_id && !a.cloudflare_record_id)
                    ? '<span class="badge bg-dark" style="font-size:0.7rem">sin CF</span>' : '';

                let nsInfo = '';
                if (a.cloudflare_nameservers && !a.is_subdomain) {
                    try {
                        const ns = JSON.parse(a.cloudflare_nameservers);
                        if (Array.isArray(ns) && ns.length) {
                            nsInfo = '<br><small class="text-info" style="font-size:0.75rem"><i class="bi bi-exclamation-circle"></i> NS: ' + ns.join(', ') + '</small>';
                        }
                    } catch(e) {
                        nsInfo = '<br><small class="text-info" style="font-size:0.75rem"><i class="bi bi-exclamation-circle"></i> NS: ' + a.cloudflare_nameservers + '</small>';
                    }
                }

                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f3f4f6;" id="alias-row-${a.id}">
                    <div>
                        <strong style="font-size:0.85rem">${a.domain}</strong>
                        ${a.include_www == 1 ? '<small class="text-muted">+ www</small>' : ''}
                        ${statusBadge} ${cfBadge}
                        ${nsInfo}
                    </div>
                    <button class="btn btn-sm btn-outline-danger" style="padding:2px 6px;font-size:0.75rem;" onclick="removeAlias(${tenantId}, ${a.id}, '${a.domain}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
            });
            listEl.innerHTML = html;
        })
        .catch(() => {
            listEl.innerHTML = '<small class="text-danger">Error de conexión.</small>';
            countEl.textContent = '0';
        });
}

function submitAlias(tenantId) {
    const input = document.getElementById('alias-input-' + tenantId);
    const domain = input.value.trim().toLowerCase();
    const includeWww = document.getElementById('alias-www-' + tenantId).checked;
    const skipCf = document.getElementById('alias-skipcf-' + tenantId).checked;

    if (!domain) {
        Swal.fire({ icon: 'warning', title: 'Dominio requerido', text: 'Introduce un dominio para añadir como alias.', confirmButtonColor: '#667eea' });
        return;
    }

    if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/.test(domain)) {
        Swal.fire({ icon: 'warning', title: 'Formato inválido', text: 'Introduce un dominio válido (ej: midominio.com).', confirmButtonColor: '#667eea' });
        return;
    }

    Swal.fire({ title: 'Configurando...', html: 'Añadiendo alias y configurando servidor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const body = new URLSearchParams();
    body.append('_csrf_token', csrfToken);
    body.append('domain', domain);
    body.append('include_www', includeWww ? '1' : '');
    body.append('skip_cloudflare', skipCf ? '1' : '');

    fetch(`/customer/tenant/${tenantId}/aliases/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            let msg = data.message || 'Alias añadido.';
            if (data.nameservers && data.nameservers.length) {
                msg += '<br><br><strong>Configura estos nameservers en tu registrador:</strong><br>';
                msg += '<div class="bg-light p-2 rounded mt-2" style="text-align:left">';
                data.nameservers.forEach((ns, i) => { msg += `<code>NS${i+1}: ${ns}</code><br>`; });
                msg += '</div>';
                msg += '<br><small class="text-muted">La propagación DNS puede tardar hasta 48h.</small>';
            } else if (data.dns_info) {
                msg += '<br><small class="text-muted">' + data.dns_info + '</small>';
            }
            Swal.fire({ icon: 'success', title: 'Alias Añadido', html: msg, confirmButtonColor: '#667eea' });
            input.value = '';
            loadAliases(tenantId);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo añadir el alias.', confirmButtonColor: '#667eea' });
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#667eea' });
    });
}

function removeAlias(tenantId, aliasId, domain) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar Alias',
        html: `¿Seguro que deseas eliminar <strong>${domain}</strong>?`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d'
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const body = new URLSearchParams();
        body.append('_csrf_token', csrfToken);
        body.append('alias_id', aliasId);

        fetch(`/customer/tenant/${tenantId}/aliases/remove`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Eliminado', text: data.message, confirmButtonColor: '#667eea', timer: 2000, timerProgressBar: true });
                loadAliases(tenantId);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo eliminar.', confirmButtonColor: '#667eea' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar.', confirmButtonColor: '#667eea' });
        });
    });
}
</script>
@endsection
