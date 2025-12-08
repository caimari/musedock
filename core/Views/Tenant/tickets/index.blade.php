@extends('layouts.app')

@section('title', 'Tickets de Soporte')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="fas fa-ticket-alt me-2"></i>
                Tickets de Soporte
            </h1>
            <p class="text-muted mb-0">Gestiona tus solicitudes de soporte técnico</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= admin_url('tickets/create') ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nuevo Ticket
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="fas fa-inbox fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total</h6>
                            <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="fas fa-folder-open fa-2x text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Abiertos</h6>
                            <h3 class="mb-0"><?= $stats['open'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="fas fa-spinner fa-2x text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">En Progreso</h6>
                            <h3 class="mb-0"><?= $stats['in_progress'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Resueltos</h6>
                            <h3 class="mb-0"><?= $stats['resolved'] ?? 0 ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= admin_url('tickets') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="open" <?= ($filters['status'] ?? '') === 'open' ? 'selected' : '' ?>>Abierto</option>
                        <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En Progreso</option>
                        <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resuelto</option>
                        <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Cerrado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Prioridad</label>
                    <select name="priority" class="form-select">
                        <option value="">Todas las prioridades</option>
                        <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                        <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="normal" <?= ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Baja</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-2"></i>Filtrar
                    </button>
                    <a href="<?= admin_url('tickets') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Tickets -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($tickets)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay tickets</h5>
                    <p class="text-muted">Crea tu primer ticket de soporte</p>
                    <a href="<?= admin_url('tickets/create') ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Crear Ticket
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0">ID</th>
                                <th class="border-0">Asunto</th>
                                <th class="border-0">Estado</th>
                                <th class="border-0">Prioridad</th>
                                <th class="border-0">Creado</th>
                                <th class="border-0">Actualizado</th>
                                <th class="border-0 text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td class="align-middle">
                                        <strong>#<?= $ticket->id ?></strong>
                                    </td>
                                    <td class="align-middle">
                                        <a href="<?= admin_url('tickets/' . $ticket->id) ?>" class="text-decoration-none">
                                            <strong><?= htmlspecialchars($ticket->subject) ?></strong>
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <?php
                                        $statusBadges = [
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'resolved' => 'success',
                                            'closed' => 'secondary'
                                        ];
                                        $statusLabels = [
                                            'open' => 'Abierto',
                                            'in_progress' => 'En Progreso',
                                            'resolved' => 'Resuelto',
                                            'closed' => 'Cerrado'
                                        ];
                                        $badge = $statusBadges[$ticket->status] ?? 'secondary';
                                        $label = $statusLabels[$ticket->status] ?? $ticket->status;
                                        ?>
                                        <span class="badge bg-<?= $badge ?>">
                                            <?= $label ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <?php
                                        $priorityBadges = [
                                            'urgent' => 'danger',
                                            'high' => 'warning',
                                            'normal' => 'info',
                                            'low' => 'secondary'
                                        ];
                                        $priorityLabels = [
                                            'urgent' => 'Urgente',
                                            'high' => 'Alta',
                                            'normal' => 'Normal',
                                            'low' => 'Baja'
                                        ];
                                        $priorityBadge = $priorityBadges[$ticket->priority] ?? 'secondary';
                                        $priorityLabel = $priorityLabels[$ticket->priority] ?? $ticket->priority;
                                        ?>
                                        <span class="badge bg-<?= $priorityBadge ?>">
                                            <?= $priorityLabel ?>
                                        </span>
                                    </td>
                                    <td class="align-middle text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($ticket->created_at)) ?>
                                    </td>
                                    <td class="align-middle text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($ticket->updated_at)) ?>
                                    </td>
                                    <td class="align-middle text-end">
                                        <a href="<?= admin_url('tickets/' . $ticket->id) ?>" class="btn btn-sm btn-outline-primary" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
@endsection
