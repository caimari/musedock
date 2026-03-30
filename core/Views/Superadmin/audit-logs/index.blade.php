@extends('layouts.app')

@section('title', $title ?? 'Auditoría de Seguridad')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-shield-lock me-2"></i>
                        Auditoría de Seguridad
                    </h1>
                    <p class="text-muted mb-0">Registro de actividad y eventos del sistema (GDPR Compliant)</p>
                </div>
                <div>
                    <a href="/musedock/audit-logs/export?<?php echo http_build_query($filters ?? []); ?>" class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                        Exportar CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-list-check text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Logs</h6>
                            <h4 class="mb-0"><?php echo number_format($stats['total_logs'] ?? 0); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-people text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Usuarios Únicos</h6>
                            <h4 class="mb-0"><?php echo number_format($stats['unique_users'] ?? 0); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-calendar-check text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Días Registrados</h6>
                            <h4 class="mb-0"><?php echo $stats['days_logged'] ?? 0; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-clock-history text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Último Log</h6>
                            <h4 class="mb-0 fs-6"><?php echo $stats['last_log'] ? date('d/m/Y H:i', strtotime($stats['last_log'])) : '-'; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>
                Filtros de Búsqueda
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="/musedock/audit-logs" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Buscar...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Acción</label>
                    <select class="form-select" name="action">
                        <option value="">Todas</option>
                        <?php foreach ($actions ?? [] as $act): ?>
                            <option value="<?php echo htmlspecialchars($act); ?>" <?php echo ($filters['action'] ?? '') === $act ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo de Recurso</label>
                    <select class="form-select" name="resource_type">
                        <option value="">Todos</option>
                        <?php foreach ($resourceTypes ?? [] as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filters['resource_type'] ?? '') === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Logs -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table me-2"></i>
                Registros de Auditoría (<?php echo number_format($totalRecords ?? 0); ?>)
            </h5>
            <span class="badge bg-primary">Página <?php echo $currentPage ?? 1; ?> de <?php echo $totalPages ?? 1; ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Recurso</th>
                            <th>IP</th>
                            <th>Fecha/Hora</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No se encontraron registros
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $log['id']; ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?></small>
                                            <br>
                                            <span class="badge bg-<?php echo $log['user_type'] === 'super_admin' ? 'danger' : 'info'; ?> badge-sm">
                                                <?php echo $log['user_type'] === 'super_admin' ? 'Superadmin' : 'Usuario'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $actionBadgeClass = 'secondary';
                                        if (strpos($log['action'], 'created') !== false) $actionBadgeClass = 'success';
                                        elseif (strpos($log['action'], 'updated') !== false) $actionBadgeClass = 'primary';
                                        elseif (strpos($log['action'], 'deleted') !== false) $actionBadgeClass = 'danger';
                                        elseif (strpos($log['action'], 'login') !== false) $actionBadgeClass = 'info';
                                        ?>
                                        <span class="badge bg-<?php echo $actionBadgeClass; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['resource_type']); ?></strong>
                                        <br>
                                        <small class="text-muted">ID: <?php echo $log['resource_id'] ?? 'N/A'; ?></small>
                                    </td>
                                    <td>
                                        <code class="text-muted small"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php echo date('d/m/Y', strtotime($log['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES); ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white">
            <nav aria-label="Paginación de logs">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php
                    $currentFilters = $filters ?? [];
                    $queryString = http_build_query($currentFilters);
                    ?>

                    <!-- Primera página -->
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=1">Primera</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $currentPage - 1; ?>">Anterior</a>
                        </li>
                    <?php endif; ?>

                    <!-- Páginas numeradas -->
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Última página -->
                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $currentPage + 1; ?>">Siguiente</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $totalPages; ?>">Última</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para detalles del log -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>
                    Detalles del Log
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Contenido dinámico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(log) {
    const content = `
        <div class="row g-3">
            <div class="col-md-6">
                <strong>ID:</strong> #${log.id}
            </div>
            <div class="col-md-6">
                <strong>Fecha/Hora:</strong> ${log.created_at}
            </div>
            <div class="col-md-6">
                <strong>Usuario:</strong> ${log.user_name}
            </div>
            <div class="col-md-6">
                <strong>Email:</strong> ${log.user_email}
            </div>
            <div class="col-md-6">
                <strong>Tipo Usuario:</strong> <span class="badge bg-${log.user_type === 'super_admin' ? 'danger' : 'info'}">${log.user_type}</span>
            </div>
            <div class="col-md-6">
                <strong>Acción:</strong> <span class="badge bg-secondary">${log.action}</span>
            </div>
            <div class="col-md-6">
                <strong>Tipo Recurso:</strong> ${log.resource_type}
            </div>
            <div class="col-md-6">
                <strong>ID Recurso:</strong> ${log.resource_id || 'N/A'}
            </div>
            <div class="col-md-6">
                <strong>IP:</strong> <code>${log.ip_address || 'N/A'}</code>
            </div>
            <div class="col-md-12">
                <strong>User Agent:</strong><br>
                <small class="text-muted">${log.user_agent || 'N/A'}</small>
            </div>
            <div class="col-12">
                <strong>Datos Adicionales:</strong>
                <pre class="bg-light p-3 rounded mt-2"><code>${JSON.stringify(log.data, null, 2)}</code></pre>
            </div>
        </div>
    `;

    document.getElementById('logDetailsContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    modal.show();
}
</script>

<style>
.badge-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}
</style>
@endsection
