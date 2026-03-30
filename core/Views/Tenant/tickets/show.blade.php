@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->id . ' - ' . $ticket->subject)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= admin_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= admin_url('tickets') ?>">Tickets</a></li>
                    <li class="breadcrumb-item active">Ticket #<?= $ticket->id ?></li>
                </ol>
            </nav>
            <h1 class="h4 mb-2">
                <i class="fas fa-ticket-alt me-2"></i>
                Ticket #<?= $ticket->id ?>
            </h1>
            <h2 class="h5 text-muted mb-0"><?= htmlspecialchars($ticket->subject) ?></h2>
        </div>
        <div class="col-md-4 text-end">
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
            <span class="badge bg-<?= $badge ?> fs-6 me-2" id="ticketStatusBadge">
                <?= $label ?>
            </span>

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
            <span class="badge bg-<?= $priorityBadge ?> fs-6">
                <?= $priorityLabel ?>
            </span>
        </div>
    </div>

    <div class="row">
        <!-- Conversación -->
        <div class="col-lg-8">
            <!-- Mensaje Original -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <strong>Descripción del Problema</strong>
                        </div>
                        <small class="text-muted">
                            <?= date('d/m/Y H:i', strtotime($ticket->created_at)) ?>
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($ticket->description) ?></p>
                </div>
            </div>

            <!-- Mensajes/Respuestas -->
            <?php if (!empty($ticket->messages)): ?>
                <h5 class="mb-3">
                    <i class="fas fa-comments me-2"></i>
                    Conversación (<?= count($ticket->messages) ?>)
                </h5>

                <?php foreach ($ticket->messages as $message): ?>
                    <?php
                    $isStaff = $message->user_type === 'super_admin';
                    $userName = $message->user['name'] ?? 'Usuario';
                    $userEmail = $message->user['email'] ?? '';
                    ?>

                    <div class="card border-0 shadow-sm mb-3 <?= $isStaff ? 'border-start border-primary border-3' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex mb-2">
                                <div class="flex-shrink-0">
                                    <?php if ($isStaff): ?>
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($userName) ?></strong>
                                            <?php if ($isStaff): ?>
                                                <span class="badge bg-primary ms-2">Soporte</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($userEmail) ?></small>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($message->created_at)) ?>
                                        </small>
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

            <!-- Formulario de Respuesta -->
            <?php if ($ticket->status !== 'closed'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <i class="fas fa-reply me-2"></i>
                        Agregar Respuesta
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= admin_url('tickets/' . $ticket->id . '/reply') ?>" id="replyForm">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <textarea
                                    class="form-control"
                                    name="message"
                                    rows="5"
                                    placeholder="Escribe tu respuesta aquí..."
                                    required
                                ></textarea>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary" id="replyBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Respuesta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Este ticket está cerrado. No se pueden agregar más respuestas.
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Información del Ticket -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>
                    Información
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Estado</small>
                        <strong id="ticketStatus"><?= $label ?></strong>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Prioridad</small>
                        <strong><?= $priorityLabel ?></strong>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Creado</small>
                        <strong><?= date('d/m/Y H:i', strtotime($ticket->created_at)) ?></strong>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Última Actualización</small>
                        <strong><?= date('d/m/Y H:i', strtotime($ticket->updated_at)) ?></strong>
                    </div>

                    <?php if ($ticket->resolved_at): ?>
                        <div class="mb-0">
                            <small class="text-muted d-block">Resuelto</small>
                            <strong class="text-success">
                                <?= date('d/m/Y H:i', strtotime($ticket->resolved_at)) ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones -->
            <?php if ($ticket->status === 'open' || $ticket->status === 'in_progress'): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header">
                        <i class="fas fa-cog me-2"></i>
                        Acciones
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            Puedes marcar este ticket como resuelto una vez que tu problema haya sido solucionado.
                        </p>
                        <button type="button" class="btn btn-success w-100" onclick="changeStatus('resolved')">
                            <i class="fas fa-check me-2"></i>Marcar como Resuelto
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ticket->status === 'resolved'): ?>
                <div class="card border-0 shadow-sm mb-3 border-success">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Ticket Resuelto</h5>
                        <p class="small text-muted mb-3">
                            Este ticket ha sido marcado como resuelto.
                        </p>
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="changeStatus('open')">
                            <i class="fas fa-redo me-2"></i>Reabrir Ticket
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Eliminar Ticket -->
            <div class="card border-0 shadow-sm border-danger">
                <div class="card-body">
                    <h6 class="text-danger mb-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Zona de Peligro
                    </h6>
                    <p class="small text-muted mb-3">
                        Eliminar este ticket es permanente y no se puede deshacer.
                    </p>
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="deleteTicket()">
                        <i class="fas fa-trash me-2"></i>Eliminar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cambiar estado del ticket
function changeStatus(newStatus) {
    if (!confirm('¿Estás seguro de cambiar el estado de este ticket?')) {
        return;
    }

    fetch('<?= admin_url('tickets/' . $ticket->id . '/status') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_csrf=<?= csrf_token() ?>&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error al cambiar el estado: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cambiar el estado');
    });
}

// Eliminar ticket
function deleteTicket() {
    if (!confirm('¿Estás seguro de que quieres eliminar este ticket? Esta acción no se puede deshacer.')) {
        return;
    }

    fetch('<?= admin_url('tickets/' . $ticket->id) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: '_csrf=<?= csrf_token() ?>&_method=DELETE'
    })
    .then(response => {
        if (response.ok) {
            window.location.href = '<?= admin_url('tickets') ?>';
        } else {
            alert('Error al eliminar el ticket');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el ticket');
    });
}

// Deshabilitar botón al enviar respuesta
document.getElementById('replyForm')?.addEventListener('submit', function() {
    const replyBtn = document.getElementById('replyBtn');
    replyBtn.disabled = true;
    replyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
});
</script>
@endsection
