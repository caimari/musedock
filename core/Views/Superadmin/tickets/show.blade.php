@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->id)

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/musedock/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/musedock/tickets">Tickets</a></li>
                    <li class="breadcrumb-item active">Ticket #<?= $ticket->id ?></li>
                </ol>
            </nav>
            <h1 class="h4 mb-0">
                Ticket #<?= $ticket->id ?> - <?= htmlspecialchars($ticket->subject) ?>
            </h1>
            <p class="text-muted">Tenant: <strong><?= htmlspecialchars($tenant['name'] ?? 'N/A') ?></strong> (<?= $tenant['domain'] ?? '' ?>)</p>
        </div>
        <div class="col-md-4 text-end">
            <?php
            $badges = ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
            $labels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
            ?>
            <span class="badge bg-<?= $badges[$ticket->status] ?? 'secondary' ?> fs-6">
                <?= $labels[$ticket->status] ?? $ticket->status ?>
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Descripción Original -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light"><strong>Descripción del Problema</strong></div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($ticket->description) ?></p>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (!empty($ticket->messages)): ?>
                <h5 class="mb-3">Conversación (<?= count($ticket->messages) ?>)</h5>
                <?php foreach ($ticket->messages as $message): ?>
                    <?php $isStaff = $message->user_type === 'super_admin'; ?>
                    <div class="card border-0 shadow-sm mb-3 <?= $isStaff ? 'border-start border-primary border-3' : '' ?> <?= $message->is_internal ? 'bg-light' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex mb-2">
                                <div class="flex-shrink-0">
                                    <?php if ($isStaff): ?>
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?= htmlspecialchars($message->user['name'] ?? 'Usuario') ?></strong>
                                            <?php if ($isStaff): ?>
                                                <span class="badge bg-primary ms-2">Soporte</span>
                                            <?php endif; ?>
                                            <?php if ($message->is_internal): ?>
                                                <span class="badge bg-warning ms-2">Nota Interna</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($message->created_at)) ?></small>
                                    </div>
                                    <div class="mt-2">
                                        <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($message->message) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Formulario Respuesta -->
            <?php if ($ticket->status !== 'closed'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header">Agregar Respuesta</div>
                    <div class="card-body">
                        <form method="POST" action="/musedock/tickets/<?= $ticket->id ?>/reply">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <textarea class="form-control" name="message" rows="5" placeholder="Escribe tu respuesta..." required></textarea>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="isInternal">
                                <label class="form-check-label" for="isInternal">
                                    <i class="fas fa-lock me-1"></i>Nota interna (solo visible para staff)
                                </label>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Respuesta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Asignación -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header">Asignación</div>
                <div class="card-body">
                    <select class="form-select" id="assignSelect" onchange="assignTicket()">
                        <option value="unassigned" <?= !$ticket->assigned_to ? 'selected' : '' ?>>Sin asignar</option>
                        <?php foreach ($superAdmins as $admin): ?>
                            <option value="<?= $admin['id'] ?>" <?= $ticket->assigned_to == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Estado -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header">Cambiar Estado</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($ticket->status !== 'in_progress'): ?>
                            <button class="btn btn-info btn-sm" onclick="changeStatus('in_progress')">
                                <i class="fas fa-spinner me-2"></i>En Progreso
                            </button>
                        <?php endif; ?>
                        <?php if ($ticket->status !== 'resolved'): ?>
                            <button class="btn btn-success btn-sm" onclick="changeStatus('resolved')">
                                <i class="fas fa-check me-2"></i>Resuelto
                            </button>
                        <?php endif; ?>
                        <?php if ($ticket->status !== 'closed'): ?>
                            <button class="btn btn-secondary btn-sm" onclick="changeStatus('closed')">
                                <i class="fas fa-times me-2"></i>Cerrar
                            </button>
                        <?php endif; ?>
                        <?php if ($ticket->status !== 'open'): ?>
                            <button class="btn btn-warning btn-sm" onclick="changeStatus('open')">
                                <i class="fas fa-redo me-2"></i>Reabrir
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Eliminar -->
            <div class="card border-0 shadow-sm border-danger">
                <div class="card-body">
                    <h6 class="text-danger">Zona de Peligro</h6>
                    <button class="btn btn-outline-danger btn-sm w-100" onclick="deleteTicket()">
                        <i class="fas fa-trash me-2"></i>Eliminar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeStatus(status) {
    if (!confirm('¿Cambiar el estado del ticket?')) return;
    fetch('/musedock/tickets/<?= $ticket->id ?>/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_csrf=<?= csrf_token() ?>&status=${status}`
    }).then(r => r.json()).then(d => d.success ? location.reload() : alert('Error'));
}

function assignTicket() {
    const assigned = document.getElementById('assignSelect').value;
    fetch('/musedock/tickets/<?= $ticket->id ?>/assign', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_csrf=<?= csrf_token() ?>&assigned_to=${assigned}`
    }).then(r => r.json()).then(d => d.success ? location.reload() : alert('Error'));
}

function deleteTicket() {
    if (!confirm('¿Eliminar este ticket? No se puede deshacer.')) return;
    fetch('/musedock/tickets/<?= $ticket->id ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_csrf=<?= csrf_token() ?>&_method=DELETE'
    }).then(r => r.ok ? location.href = '/musedock/tickets' : alert('Error'));
}
</script>
@endsection
