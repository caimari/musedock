@extends('Customer.layout')

@section('styles')
<style>
    .transfer-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .transfer-card .card-header {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 30px;
        text-align: center;
    }
    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
    }
    .step {
        display: flex;
        align-items: center;
        color: #6c757d;
    }
    .step.active .step-number {
        background: #11998e;
        color: white;
    }
    .step.completed .step-number {
        background: #28a745;
        color: white;
    }
    .step-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 8px;
    }
    .step-connector {
        width: 50px;
        height: 2px;
        background: #e9ecef;
        margin: 0 10px;
    }
    .transfer-check-result {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
    }
    .contact-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .transfers-list {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
    }
    .transfer-item {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #6c757d;
    }
    .transfer-item.pending { border-left-color: #ffc107; }
    .transfer-item.processing { border-left-color: #17a2b8; }
    .transfer-item.completed { border-left-color: #28a745; }
    .transfer-item.failed { border-left-color: #dc3545; }
    .sandbox-warning {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>
@endsection

@section('content')
<?php
    $opMode = $openprovider_mode ?? 'live';
?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1Indicator">
                <span class="step-number">1</span>
                <span>Verificar</span>
            </div>
            <div class="step-connector"></div>
            <div class="step" id="step2Indicator">
                <span class="step-number">2</span>
                <span>Datos</span>
            </div>
            <div class="step-connector"></div>
            <div class="step" id="step3Indicator">
                <span class="step-number">3</span>
                <span>Confirmar</span>
            </div>
        </div>

        <?php if ($opMode === 'sandbox'): ?>
        <div class="sandbox-warning">
            <h6 class="mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Modo Sandbox</h6>
            <p class="mb-0 small">
                Las transferencias se procesaran en el <strong>entorno de pruebas</strong>.
            </p>
        </div>
        <?php endif; ?>

        <div class="card transfer-card">
            <div class="card-header">
                <i class="bi bi-arrow-left-right" style="font-size: 2.5rem;"></i>
                <h3 class="mb-0 mt-2">Transferir Dominio</h3>
                <p class="mb-0 opacity-75">Transfiere tu dominio a MuseDock</p>
            </div>
            <div class="card-body p-4">

                <!-- Step 1: Verificar dominio -->
                <div id="step1" class="step-content">
                    <h5 class="mb-3"><i class="bi bi-search me-2"></i>Paso 1: Verificar Dominio</h5>

                    <form id="checkForm" onsubmit="checkTransferability(event)">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Dominio a transferir</label>
                                <input type="text" class="form-control form-control-lg" id="domainInput"
                                       placeholder="ejemplo.com" required>
                                <small class="text-muted">Introduce el dominio completo que deseas transferir</small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="checkBtn">
                                    <i class="bi bi-search me-1"></i>Verificar
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="checkResult" class="transfer-check-result" style="display:none;">
                        <!-- Resultado de verificacion -->
                    </div>

                    <div class="alert alert-info mt-4">
                        <h6><i class="bi bi-info-circle me-2"></i>Antes de transferir:</h6>
                        <ul class="mb-0 small">
                            <li>Desbloquea tu dominio en el registrador actual</li>
                            <li>Obtén el código de autorización (EPP code / Auth code)</li>
                            <li>Asegúrate de que el dominio no expire en los próximos 15 días</li>
                            <li>Verifica que el email del propietario esté actualizado</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 2: Datos de contacto -->
                <div id="step2" class="step-content" style="display:none;">
                    <h5 class="mb-3"><i class="bi bi-person me-2"></i>Paso 2: Datos del Registrante</h5>

                    <form id="transferForm">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="domain" id="transferDomain">

                        <div class="mb-4">
                            <label class="form-label fw-bold">Código de Autorización (Auth Code) *</label>
                            <input type="text" class="form-control form-control-lg" name="auth_code"
                                   placeholder="Introduce el código EPP" required>
                            <small class="text-muted">Este código lo proporciona tu registrador actual</small>
                        </div>

                        <?php if (!empty($contacts)): ?>
                        <div class="contact-section">
                            <h6><i class="bi bi-person-check me-2"></i>Usar contacto existente</h6>
                            <select class="form-select" name="owner_existing" id="ownerExisting" onchange="toggleContactForm()">
                                <option value="">-- Crear nuevo contacto --</option>
                                <?php foreach ($contacts as $contact): ?>
                                <option value="<?= $contact['id'] ?>">
                                    <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                    (<?= htmlspecialchars($contact['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div id="newContactForm" class="contact-section">
                            <h6><i class="bi bi-person-plus me-2"></i>Datos del Propietario</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" name="owner_first_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" name="owner_last_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Empresa</label>
                                    <input type="text" class="form-control" name="owner_company">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="owner_email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" name="owner_phone" placeholder="+34612345678">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">País *</label>
                                    <select class="form-select" name="owner_country">
                                        <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= $code ?>" <?= $code === 'ES' ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Dirección *</label>
                                    <input type="text" class="form-control" name="owner_street">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" name="owner_number">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Código Postal *</label>
                                    <input type="text" class="form-control" name="owner_zipcode">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ciudad *</label>
                                    <input type="text" class="form-control" name="owner_city">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Provincia</label>
                                    <input type="text" class="form-control" name="owner_state">
                                </div>
                            </div>
                        </div>

                        <div class="contact-section">
                            <h6><i class="bi bi-hdd-stack me-2"></i>Tipo de Servicio</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="hosting_type" id="hostingMusedock" value="musedock_hosting" checked>
                                <label class="form-check-label" for="hostingMusedock">
                                    <strong>DNS + Hosting MuseDock</strong>
                                    <br><small class="text-muted">Se creará un sitio web automáticamente</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hosting_type" id="hostingDnsOnly" value="dns_only">
                                <label class="form-check-label" for="hostingDnsOnly">
                                    <strong>Solo Gestión DNS</strong>
                                    <br><small class="text-muted">Solo zona DNS vacía en Cloudflare</small>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                                <i class="bi bi-arrow-left me-1"></i>Volver
                            </button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                                Continuar<i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Confirmar -->
                <div id="step3" class="step-content" style="display:none;">
                    <h5 class="mb-3"><i class="bi bi-check-circle me-2"></i>Paso 3: Confirmar Transferencia</h5>

                    <div id="transferSummary" class="contact-section">
                        <!-- Resumen -->
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="bi bi-clock me-2"></i>Tiempo de transferencia</h6>
                        <p class="mb-0 small">
                            La transferencia puede tardar entre 1 y 5 días dependiendo del registrador actual.
                            Recibirás un email de confirmación cuando se complete.
                        </p>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" onclick="goToStep(2)">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="initiateTransfer()">
                            <i class="bi bi-check2-circle me-1"></i>Iniciar Transferencia
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <?php if (!empty($transfers)): ?>
        <!-- Transferencias recientes -->
        <div class="mt-4">
            <h5><i class="bi bi-clock-history me-2"></i>Transferencias Recientes</h5>
            <div class="transfers-list">
                <?php foreach ($transfers as $transfer): ?>
                <div class="transfer-item <?= $transfer['status'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($transfer['domain']) ?></strong>
                            <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($transfer['created_at'])) ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?= $transfer['status'] === 'completed' ? 'success' : ($transfer['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($transfer['status']) ?>
                            </span>
                            <br>
                            <a href="/customer/transfer-domain/status/<?= $transfer['id'] ?>" class="small">Ver detalles</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentStep = 1;
let transferData = {};

function checkTransferability(event) {
    event.preventDefault();

    const domain = document.getElementById('domainInput').value.trim().toLowerCase();

    if (!domain || !domain.includes('.')) {
        Swal.fire('Error', 'Introduce un dominio válido', 'error');
        return;
    }

    const btn = document.getElementById('checkBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verificando...';

    fetch(`/customer/transfer-domain/check?domain=${encodeURIComponent(domain)}`)
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search me-1"></i>Verificar';

        const resultDiv = document.getElementById('checkResult');
        resultDiv.style.display = 'block';

        if (data.success && data.transferable) {
            transferData.domain = domain;
            transferData.price = data.price;
            transferData.currency = data.currency;

            resultDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill text-success me-3" style="font-size:2rem;"></i>
                    <div>
                        <h5 class="mb-1 text-success">¡Dominio transferible!</h5>
                        <p class="mb-0"><strong>${domain}</strong></p>
                        ${data.price ? `<p class="mb-0">Precio: <strong>${data.price.toFixed(2)} ${data.currency}</strong></p>` : ''}
                    </div>
                </div>
                <button type="button" class="btn btn-success mt-3" onclick="goToStep(2)">
                    <i class="bi bi-arrow-right me-1"></i>Continuar con la transferencia
                </button>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-x-circle-fill text-danger me-3" style="font-size:2rem;"></i>
                    <div>
                        <h5 class="mb-1 text-danger">No transferible</h5>
                        <p class="mb-0">${data.reason || 'El dominio no puede transferirse en este momento'}</p>
                    </div>
                </div>
            `;
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search me-1"></i>Verificar';
        Swal.fire('Error', 'Error al verificar el dominio', 'error');
    });
}

function goToStep(step) {
    // Validar antes de avanzar
    if (step > currentStep) {
        if (currentStep === 1 && !transferData.domain) {
            Swal.fire('Error', 'Primero verifica el dominio', 'warning');
            return;
        }
        if (currentStep === 2) {
            const form = document.getElementById('transferForm');
            const authCode = form.querySelector('[name="auth_code"]').value;
            if (!authCode) {
                Swal.fire('Error', 'El código de autorización es requerido', 'warning');
                return;
            }
        }
    }

    // Ocultar todos los pasos
    document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none');

    // Mostrar paso actual
    document.getElementById(`step${step}`).style.display = 'block';

    // Actualizar indicadores
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step${i}Indicator`);
        indicator.classList.remove('active', 'completed');
        if (i < step) {
            indicator.classList.add('completed');
        } else if (i === step) {
            indicator.classList.add('active');
        }
    }

    // Si es paso 3, generar resumen
    if (step === 3) {
        generateSummary();
    }

    currentStep = step;

    // Actualizar campo oculto
    document.getElementById('transferDomain').value = transferData.domain;
}

function toggleContactForm() {
    const existing = document.getElementById('ownerExisting').value;
    document.getElementById('newContactForm').style.display = existing ? 'none' : 'block';
}

function generateSummary() {
    const form = document.getElementById('transferForm');
    const formData = new FormData(form);
    const hostingType = formData.get('hosting_type');

    const summaryDiv = document.getElementById('transferSummary');
    summaryDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <h6 class="text-muted">Dominio</h6>
                <p class="mb-0 fw-bold">${transferData.domain}</p>
            </div>
            <div class="col-md-6 mb-3">
                <h6 class="text-muted">Tipo de Servicio</h6>
                <p class="mb-0">${hostingType === 'musedock_hosting' ? 'DNS + Hosting MuseDock' : 'Solo Gestión DNS'}</p>
            </div>
            ${transferData.price ? `
            <div class="col-md-6 mb-3">
                <h6 class="text-muted">Precio</h6>
                <p class="mb-0 fw-bold text-success">${transferData.price.toFixed(2)} ${transferData.currency}</p>
            </div>
            ` : ''}
        </div>
    `;
}

function initiateTransfer() {
    Swal.fire({
        title: 'Iniciar Transferencia',
        text: `¿Confirmas la transferencia de ${transferData.domain}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, transferir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            processTransfer();
        }
    });
}

function processTransfer() {
    Swal.fire({
        title: 'Iniciando transferencia...',
        html: '<div class="spinner-border text-primary"></div><p class="mt-2">Conectando con OpenProvider...</p>',
        allowOutsideClick: false,
        showConfirmButton: false
    });

    const form = document.getElementById('transferForm');
    const formData = new FormData(form);

    fetch('/customer/transfer-domain/initiate', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Transferencia Iniciada!',
                html: `<p>${data.message}</p>`,
                confirmButtonText: 'Ver Estado'
            }).then(() => {
                window.location.href = data.redirect || '/customer/dashboard';
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo iniciar la transferencia', 'error');
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
</script>
@endsection
