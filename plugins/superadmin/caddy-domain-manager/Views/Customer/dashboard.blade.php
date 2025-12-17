@extends('Customer.layout')

@section('styles')
<style>
    .stats-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.3s ease;
        border: 1px solid #f0f0f0;
    }
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        border-color: #667eea;
    }
    .stats-card .icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 18px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .stats-card .number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
        line-height: 1;
    }
    .stats-card .label {
        color: #666;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .tenant-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 18px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #f0f0f0;
    }
    .tenant-card:hover {
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.12);
        border-color: #e0e0e0;
        transform: translateX(5px);
    }
    .tenant-card .domain {
        font-size: 1.1rem;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 10px;
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
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 15px;
    }
    .badge-plan-free {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }
    .badge-plan-custom {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    .badge-status-active {
        background: #28a745;
        color: white;
    }
    .badge-status-error {
        background: #dc3545;
        color: white;
    }
    .badge-status-waiting_ns_change {
        background: #ffc107;
        color: #333;
    }
</style>
@endsection

@section('content')
<h2 class="mb-4">Dashboard</h2>

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
<h4 class="mt-4 mb-3">Mis Sitios</h4>

<?php if (empty($tenants)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Aun no tienes sitios web. Solicita tu primer subdominio FREE o incorpora tu dominio personalizado!
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

        <div class="mt-3">
            <?php if ($tenant['status'] === 'active'): ?>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
               class="btn btn-sm btn-primary" target="_blank">
                <i class="bi bi-gear"></i> Panel de Admin
            </a>
            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>"
               class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="bi bi-eye"></i> Ver Sitio
            </a>
            <?php elseif ($tenant['status'] === 'waiting_ns_change'): ?>
            <span class="text-warning">
                <i class="bi bi-hourglass-split"></i> Esperando cambio de nameservers...
            </span>
            <?php endif; ?>

            <!-- Boton de Retry si hay problemas -->
            <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
            <button class="btn btn-sm btn-warning" onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-arrow-clockwise"></i> Reintentar
            </button>
            <?php endif; ?>

            <!-- Boton Health Check Manual -->
            <?php if ($tenant['status'] === 'active'): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')">
                <i class="bi bi-heart-pulse"></i> Verificar
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Botones de accion -->
<div class="text-center mt-4 mb-4">
    <a href="/customer/request-free-subdomain" class="btn btn-success btn-lg me-2">
        <i class="bi bi-gift"></i> Solicitar Subdominio FREE
    </a>
    <a href="/customer/request-custom-domain" class="btn btn-primary btn-lg">
        <i class="bi bi-plus-circle"></i> Solicitar Dominio Personalizado
    </a>
</div>
@endsection

@section('scripts')
<script>
function retryProvisioning(tenantId, domain) {
    Swal.fire({
        title: 'Reintentar configuracion?',
        html: `Deseas reintentar la configuracion de <strong>${domain}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, reintentar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                html: 'Reintentando configuracion...',
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
                    Swal.fire({
                        icon: 'success',
                        title: 'Exito!',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al reintentar la configuracion',
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
</script>
@endsection
