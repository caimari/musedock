@extends('layouts.app')

@section('title', __('tickets.all_tickets'))

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">
                <i class="fas fa-ticket-alt me-2"></i>
                {{ __('tickets.title') }}
            </h1>
            <p class="text-muted mb-0">{{ __('tickets.manage_all_tenants') }}</p>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('common.total') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm border-warning">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-warning"><?= $stats['open'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('tickets.status_open') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm border-info">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-info"><?= $stats['in_progress'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('tickets.status_in_progress') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm border-success">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-success"><?= $stats['resolved'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('tickets.status_resolved') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm border-secondary">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-secondary"><?= $stats['closed'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('tickets.status_closed') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm border-danger">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-danger"><?= $stats['unassigned'] ?? 0 ?></h3>
                    <small class="text-muted">{{ __('tickets.unassigned') }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/musedock/tickets" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('tenants.tenant') }}</label>
                    <select name="tenant_id" class="form-select">
                        <option value="">{{ __('tenants.all_tenants') }}</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tenant['name']) ?> (<?= $tenant['domain'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('common.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        <option value="open" <?= ($filters['status'] ?? '') === 'open' ? 'selected' : '' ?>>{{ __('tickets.status_open') }}</option>
                        <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>{{ __('tickets.status_in_progress') }}</option>
                        <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>{{ __('tickets.status_resolved') }}</option>
                        <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>{{ __('tickets.status_closed') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('tickets.priority') }}</label>
                    <select name="priority" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>{{ __('tickets.priority_urgent') }}</option>
                        <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>{{ __('tickets.priority_high') }}</option>
                        <option value="normal" <?= ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>{{ __('tickets.priority_normal') }}</option>
                        <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>{{ __('tickets.priority_low') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('tickets.assigned_to') }}</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        <option value="unassigned" <?= ($filters['assigned_to'] ?? '') === 'unassigned' ? 'selected' : '' ?>>{{ __('tickets.unassigned') }}</option>
                        <?php foreach ($superAdmins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= ($filters['assigned_to'] ?? '') == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>{{ __('common.filter') }}
                    </button>
                    <a href="/musedock/tickets" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
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
                    <h5 class="text-muted">{{ __('tickets.no_tickets') }}</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0">ID</th>
                                <th class="border-0">{{ __('tenants.tenant') }}</th>
                                <th class="border-0">{{ __('tickets.subject') }}</th>
                                <th class="border-0">{{ __('common.status') }}</th>
                                <th class="border-0">{{ __('tickets.priority') }}</th>
                                <th class="border-0">{{ __('tickets.assigned') }}</th>
                                <th class="border-0">{{ __('common.created') }}</th>
                                <th class="border-0 text-end">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td class="align-middle"><strong>#<?= $ticket['id'] ?></strong></td>
                                    <td class="align-middle">
                                        <small class="text-muted"><?= htmlspecialchars($ticket['tenant_name'] ?? 'N/A') ?></small>
                                    </td>
                                    <td class="align-middle">
                                        <a href="/musedock/tickets/<?= $ticket['id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($ticket['subject']) ?>
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <?php
                                        $badges = ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
                                        $labels = [
                                            'open' => __('tickets.status_open'),
                                            'in_progress' => __('tickets.status_in_progress'),
                                            'resolved' => __('tickets.status_resolved'),
                                            'closed' => __('tickets.status_closed')
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $badges[$ticket['status']] ?? 'secondary' ?>">
                                            <?= $labels[$ticket['status']] ?? $ticket['status'] ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <?php
                                        $pBadges = ['urgent' => 'danger', 'high' => 'warning', 'normal' => 'info', 'low' => 'secondary'];
                                        $pLabels = [
                                            'urgent' => __('tickets.priority_urgent'),
                                            'high' => __('tickets.priority_high'),
                                            'normal' => __('tickets.priority_normal'),
                                            'low' => __('tickets.priority_low')
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $pBadges[$ticket['priority']] ?? 'secondary' ?>">
                                            <?= $pLabels[$ticket['priority']] ?? $ticket['priority'] ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <?php if ($ticket['assigned_to']): ?>
                                            <small class="text-success"><i class="fas fa-user-check me-1"></i>{{ __('tickets.assigned') }}</small>
                                        <?php else: ?>
                                            <small class="text-danger"><i class="fas fa-user-times me-1"></i>{{ __('tickets.unassigned') }}</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                    </td>
                                    <td class="align-middle text-end">
                                        <a href="/musedock/tickets/<?= $ticket['id'] ?>" class="btn btn-sm btn-outline-primary">
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
