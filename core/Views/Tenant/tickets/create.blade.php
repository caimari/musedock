@extends('layouts.app')

@section('title', 'Crear Ticket de Soporte')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= admin_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= admin_url('tickets') ?>">Tickets</a></li>
                    <li class="breadcrumb-item active">Crear Ticket</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-plus-circle me-2"></i>
                Crear Ticket de Soporte
            </h1>
            <p class="text-muted">Describe tu problema o solicitud y nuestro equipo te ayudará</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Formulario -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="<?= admin_url('tickets') ?>" id="createTicketForm">
                        <?= csrf_field() ?>

                        <!-- Asunto -->
                        <div class="mb-3">
                            <label for="subject" class="form-label">
                                Asunto <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="subject"
                                name="subject"
                                placeholder="Ej: Problema con la configuración del tema"
                                required
                                maxlength="255"
                            >
                            <small class="text-muted">Resume tu problema en pocas palabras</small>
                        </div>

                        <!-- Prioridad -->
                        <div class="mb-3">
                            <label for="priority" class="form-label">
                                Prioridad <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="normal" selected>Normal - Consulta general</option>
                                <option value="low">Baja - Sin urgencia</option>
                                <option value="high">Alta - Afecta funcionamiento</option>
                                <option value="urgent">Urgente - Sitio caído</option>
                            </select>
                            <small class="text-muted">Selecciona el nivel de urgencia de tu solicitud</small>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <textarea
                                class="form-control"
                                id="description"
                                name="description"
                                rows="8"
                                placeholder="Describe detalladamente tu problema o solicitud...

Incluye:
- ¿Qué estabas haciendo cuando ocurrió el problema?
- ¿Qué resultado esperabas?
- ¿Qué resultado obtuviste?
- ¿Pasos para reproducir el problema?"
                                required
                            ></textarea>
                            <small class="text-muted">Cuantos más detalles proporciones, más rápido podremos ayudarte</small>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between">
                            <a href="<?= admin_url('tickets') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Crear Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Guía de Soporte -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle me-2"></i>
                    Guía para un buen ticket
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Para una respuesta más rápida:</h6>

                    <div class="mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-check-circle me-2"></i>Sé específico
                        </h6>
                        <p class="small text-muted mb-0">
                            Describe exactamente qué está mal y dónde lo encontraste.
                        </p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-check-circle me-2"></i>Proporciona detalles
                        </h6>
                        <p class="small text-muted mb-0">
                            Incluye capturas de pantalla, URLs y mensajes de error.
                        </p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-check-circle me-2"></i>Pasos para reproducir
                        </h6>
                        <p class="small text-muted mb-0">
                            Explica cómo podemos ver el problema nosotros mismos.
                        </p>
                    </div>

                    <div class="mb-0">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-check-circle me-2"></i>Un problema por ticket
                        </h6>
                        <p class="small text-muted mb-0">
                            Crea tickets separados para problemas diferentes.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tiempos de Respuesta -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-clock me-2"></i>
                    Tiempos de Respuesta
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="badge bg-danger me-2">Urgente</span>
                            <small>1-2 horas</small>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-warning me-2">Alta</span>
                            <small>4-6 horas</small>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-info me-2">Normal</span>
                            <small>12-24 horas</small>
                        </li>
                        <li class="mb-0">
                            <span class="badge bg-secondary me-2">Baja</span>
                            <small>24-48 horas</small>
                        </li>
                    </ul>
                    <hr>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Estos son tiempos estimados. Haremos nuestro mejor esfuerzo para responder antes.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('createTicketForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';
});
</script>
@endsection
