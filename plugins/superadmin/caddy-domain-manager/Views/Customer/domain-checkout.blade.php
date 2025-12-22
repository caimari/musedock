@extends('Customer.layout')

@section('styles')
<style>
    .checkout-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .checkout-card .card-header {
        background: #28a745;
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 30px;
        text-align: center;
    }
    .domain-display {
        font-size: 1.8rem;
        font-weight: bold;
        margin-top: 10px;
    }
    .summary-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .summary-section h6 {
        color: #6c757d;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        margin-bottom: 15px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .summary-row:last-child {
        border-bottom: none;
    }
    .summary-row.total {
        font-size: 1.2rem;
        font-weight: bold;
        color: #28a745;
        margin-top: 10px;
        padding-top: 15px;
        border-top: 2px solid #e0e0e0;
    }
    .contact-info {
        line-height: 1.8;
    }
    .feature-list {
        list-style: none;
        padding: 0;
    }
    .feature-list li {
        padding: 8px 0;
        border-bottom: 1px dashed #e0e0e0;
    }
    .feature-list li:last-child {
        border-bottom: none;
    }
    .feature-list i {
        color: #28a745;
        margin-right: 10px;
    }
    .btn-register {
        background: #28a745;
        border: none;
        padding: 15px 40px;
        font-size: 1.1rem;
        border-radius: 30px;
    }
    .btn-register:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }
    .info-box {
        background: #e3f8fc;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .sandbox-warning {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .config-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .config-badge.hosting {
        background: #d4edda;
        color: #155724;
    }
    .config-badge.dns-only {
        background: #cce5ff;
        color: #004085;
    }
    .config-badge.cloudflare {
        background: #f8d7da;
        color: #721c24;
    }
    .config-badge.custom-ns {
        background: #e2e3e5;
        color: #383d41;
    }
    .contact-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
    }
    .contact-card .contact-type {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
    }
    .contact-card .contact-name {
        font-weight: 600;
        margin-bottom: 2px;
    }
    .contact-card .contact-handle {
        font-size: 0.8rem;
        color: #28a745;
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
    .step.completed .step-number {
        background: #28a745;
        color: white;
    }
    .step.active .step-number {
        background: #007bff;
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
    .step.completed + .step-connector,
    .step-connector.completed {
        background: #28a745;
    }
</style>
@endsection

@section('content')
<?php
    $hostingType = $hosting_type ?? 'musedock_hosting';
    $nsType = $ns_type ?? 'cloudflare';
    $customNs = $custom_ns ?? [];
    $handles = $handles ?? [];
    $opMode = $openprovider_mode ?? 'live';
?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step completed">
                <span class="step-number"><i class="bi bi-check"></i></span>
                <span>Buscar</span>
            </div>
            <div class="step-connector completed"></div>
            <div class="step completed">
                <span class="step-number"><i class="bi bi-check"></i></span>
                <span>Datos</span>
            </div>
            <div class="step-connector completed"></div>
            <div class="step active">
                <span class="step-number">3</span>
                <span>Confirmar</span>
            </div>
        </div>

        <?php if ($opMode === 'sandbox'): ?>
        <div class="sandbox-warning">
            <h6 class="mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Modo Sandbox</h6>
            <p class="mb-0 small">
                Esta operacion se realizara en el <strong>entorno de pruebas</strong> de OpenProvider.
                El dominio NO sera registrado realmente.
            </p>
        </div>
        <?php endif; ?>

        <div class="card checkout-card">
            <div class="card-header">
                <i class="bi bi-bag-check" style="font-size: 2.5rem;"></i>
                <h3 class="mb-0 mt-2">Confirmar Registro</h3>
                <div class="domain-display"><?= htmlspecialchars($domain ?? '') ?></div>
            </div>
            <div class="card-body p-4">

                <div class="row">
                    <!-- Resumen del pedido -->
                    <div class="col-lg-7">
                        <div class="summary-section">
                            <h6><i class="bi bi-receipt me-2"></i>Resumen del Pedido</h6>

                            <div class="summary-row">
                                <span>Dominio</span>
                                <strong><?= htmlspecialchars($domain ?? '') ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Periodo</span>
                                <span>1 año</span>
                            </div>
                            <div class="summary-row">
                                <span>Tipo de Servicio</span>
                                <span>
                                    <?php if ($hostingType === 'musedock_hosting'): ?>
                                    <span class="config-badge hosting"><i class="bi bi-hdd-stack me-1"></i>DNS + Hosting MuseDock</span>
                                    <?php else: ?>
                                    <span class="config-badge dns-only"><i class="bi bi-globe me-1"></i>Solo Gestion DNS</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span>Nameservers</span>
                                <span>
                                    <?php if ($nsType === 'cloudflare'): ?>
                                    <span class="config-badge cloudflare"><i class="bi bi-cloud me-1"></i>Cloudflare NS</span>
                                    <?php else: ?>
                                    <span class="config-badge custom-ns"><i class="bi bi-server me-1"></i>NS Personalizados</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($nsType === 'custom' && !empty($customNs)): ?>
                            <div class="summary-row">
                                <span>NS Configurados</span>
                                <span class="text-end">
                                    <?php foreach ($customNs as $ns): ?>
                                    <code class="d-block small"><?= htmlspecialchars($ns) ?></code>
                                    <?php endforeach; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="summary-row">
                                <span>Proteccion Cloudflare</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>
                            <div class="summary-row">
                                <span>SSL/HTTPS</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>
                            <?php if ($hostingType === 'musedock_hosting'): ?>
                            <div class="summary-row">
                                <span>Hosting MuseDock</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>
                            <?php endif; ?>

                            <div class="summary-row total">
                                <span>Total</span>
                                <span><?= number_format($price ?? 0, 2) ?> <?= htmlspecialchars($currency ?? 'EUR') ?></span>
                            </div>
                        </div>

                        <!-- Contactos -->
                        <div class="summary-section">
                            <h6><i class="bi bi-people me-2"></i>Contactos del Dominio</h6>

                            <div class="row">
                                <!-- Owner -->
                                <div class="col-md-6">
                                    <div class="contact-card">
                                        <div class="contact-type">Propietario (Owner)</div>
                                        <div class="contact-name"><?= htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?></div>
                                        <div class="contact-handle"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($handles['owner'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <!-- Admin -->
                                <div class="col-md-6">
                                    <div class="contact-card">
                                        <div class="contact-type">Administrativo</div>
                                        <div class="contact-name">
                                            <?php if (($handles['admin'] ?? '') === ($handles['owner'] ?? '')): ?>
                                            <span class="text-muted">= Propietario</span>
                                            <?php else: ?>
                                            <?= htmlspecialchars(($admin_contact['first_name'] ?? $contact['first_name'] ?? '') . ' ' . ($admin_contact['last_name'] ?? $contact['last_name'] ?? '')) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-handle"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($handles['admin'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <!-- Tech -->
                                <div class="col-md-6">
                                    <div class="contact-card">
                                        <div class="contact-type">Tecnico</div>
                                        <div class="contact-name">
                                            <?php if (($handles['tech'] ?? '') === ($handles['owner'] ?? '')): ?>
                                            <span class="text-muted">= Propietario</span>
                                            <?php else: ?>
                                            <?= htmlspecialchars(($tech_contact['first_name'] ?? $contact['first_name'] ?? '') . ' ' . ($tech_contact['last_name'] ?? $contact['last_name'] ?? '')) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-handle"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($handles['tech'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <!-- Billing -->
                                <div class="col-md-6">
                                    <div class="contact-card">
                                        <div class="contact-type">Facturacion</div>
                                        <div class="contact-name">
                                            <?php if (($handles['billing'] ?? '') === ($handles['owner'] ?? '')): ?>
                                            <span class="text-muted">= Propietario</span>
                                            <?php else: ?>
                                            <?= htmlspecialchars(($billing_contact['first_name'] ?? $contact['first_name'] ?? '') . ' ' . ($billing_contact['last_name'] ?? $contact['last_name'] ?? '')) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-handle"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($handles['billing'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                            </div>

                            <a href="/customer/register-domain/contact" class="btn btn-sm btn-outline-secondary mt-3">
                                <i class="bi bi-pencil me-1"></i>Modificar contactos u opciones
                            </a>
                        </div>
                    </div>

                    <!-- Que incluye -->
                    <div class="col-lg-5">
                        <div class="summary-section">
                            <h6><i class="bi bi-gift me-2"></i>Tu Registro Incluye</h6>
                            <ul class="feature-list">
                                <li><i class="bi bi-check-circle-fill"></i>Registro por 1 año</li>
                                <li><i class="bi bi-check-circle-fill"></i>Renovacion automatica</li>
                                <li><i class="bi bi-check-circle-fill"></i>DNS gestionado por Cloudflare</li>
                                <li><i class="bi bi-check-circle-fill"></i>Certificado SSL gratuito</li>
                                <li><i class="bi bi-check-circle-fill"></i>Proteccion DDoS</li>
                                <li><i class="bi bi-check-circle-fill"></i>CDN Global</li>
                                <?php if ($hostingType === 'musedock_hosting'): ?>
                                <li><i class="bi bi-check-circle-fill"></i>Hosting MuseDock incluido</li>
                                <li><i class="bi bi-check-circle-fill"></i>Panel de administracion</li>
                                <li><i class="bi bi-check-circle-fill"></i>Sitio web preconfigurado</li>
                                <?php else: ?>
                                <li><i class="bi bi-check-circle-fill"></i>Zona DNS vacia preparada</li>
                                <li><i class="bi bi-check-circle-fill"></i>Panel de gestion DNS</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Datos del propietario resumen -->
                        <div class="summary-section">
                            <h6><i class="bi bi-person-vcard me-2"></i>Propietario del Dominio</h6>
                            <div class="contact-info">
                                <strong><?= htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?></strong>
                                <?php if (!empty($contact['company'])): ?>
                                <br><i class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($contact['company']) ?>
                                <?php endif; ?>
                                <br><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($contact['email'] ?? '') ?>
                                <br><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($contact['phone'] ?? '') ?>
                                <br><i class="bi bi-geo-alt me-1 text-muted"></i><small>
                                    <?= htmlspecialchars($contact['address_street'] ?? '') ?>
                                    <?= htmlspecialchars($contact['address_number'] ?? '') ?>,
                                    <?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>
                                    <?= htmlspecialchars($contact['address_city'] ?? '') ?>
                                    (<?= htmlspecialchars($contact['address_country'] ?? '') ?>)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($hostingType === 'musedock_hosting'): ?>
                <div class="info-box">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Informacion Importante</h6>
                    <p class="mb-0 small">
                        Al completar el registro, el dominio sera configurado automaticamente con los DNS de Cloudflare
                        y se creara tu sitio web en MuseDock apuntando a <strong><?= htmlspecialchars($domain ?? '') ?></strong>.
                        Recibiras un email con las instrucciones de acceso.
                    </p>
                </div>
                <?php else: ?>
                <div class="info-box">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Solo Gestion DNS</h6>
                    <p class="mb-0 small">
                        Has seleccionado <strong>Solo Gestion DNS</strong>. El dominio se registrara y la zona DNS
                        quedara vacia en Cloudflare para que configures tus propios registros.
                        No se creara un sitio web automaticamente.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Botones -->
                <div class="d-grid gap-2 mt-4">
                    <button type="button" class="btn btn-success btn-register btn-lg" id="registerBtn" onclick="confirmRegistration()">
                        <i class="bi bi-check2-circle me-2"></i>Confirmar Registro
                    </button>
                    <a href="/customer/register-domain/contact" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Volver a editar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const hostingType = '<?= $hostingType ?>';
const opMode = '<?= $opMode ?>';

function confirmRegistration() {
    let modeWarning = '';
    if (opMode === 'sandbox') {
        modeWarning = '<div class="alert alert-warning mt-3 mb-0"><small><i class="bi bi-exclamation-triangle me-1"></i>Modo Sandbox - Registro de prueba</small></div>';
    }

    Swal.fire({
        title: 'Confirmar Registro',
        html: `
            <p>Vas a registrar el dominio:</p>
            <h4 class="text-primary"><?= htmlspecialchars($domain ?? '') ?></h4>
            <p class="mb-0">Total: <strong><?= number_format($price ?? 0, 2) ?> <?= htmlspecialchars($currency ?? 'EUR') ?></strong></p>
            <p class="small text-muted mt-2">
                ${hostingType === 'musedock_hosting' ? '<i class="bi bi-hdd-stack me-1"></i>DNS + Hosting MuseDock' : '<i class="bi bi-globe me-1"></i>Solo Gestion DNS'}
            </p>
            ${modeWarning}
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            processRegistration();
        }
    });
}

function processRegistration() {
    document.getElementById('registerBtn').disabled = true;

    // Pasos diferentes segun tipo de hosting
    let steps;
    if (hostingType === 'musedock_hosting') {
        steps = [
            'Conectando con OpenProvider...',
            'Registrando dominio...',
            'Configurando zona DNS en Cloudflare...',
            'Creando registros DNS (@ y www)...',
            'Creando tu sitio web en MuseDock...',
            'Finalizando...'
        ];
    } else {
        steps = [
            'Conectando con OpenProvider...',
            'Registrando dominio...',
            'Creando zona DNS vacia en Cloudflare...',
            'Finalizando...'
        ];
    }

    Swal.fire({
        title: 'Registrando dominio...',
        html: `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p id="statusText">Iniciando registro...</p>
                <div class="progress mt-3" style="height: 5px;">
                    <div class="progress-bar bg-success" id="progressBar" style="width: 0%"></div>
                </div>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            const statusText = document.getElementById('statusText');
            const progressBar = document.getElementById('progressBar');
            let step = 0;
            const progressPerStep = 100 / steps.length;

            const interval = setInterval(() => {
                step++;
                if (step < steps.length) {
                    statusText.textContent = steps[step];
                    progressBar.style.width = (progressPerStep * (step + 1)) + '%';
                }
            }, 2500);

            // Hacer la peticion
            const formData = new FormData();
            formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');

            fetch('/customer/domain/register', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(interval);

                if (data.success) {
                    let nsHtml = '';
                    if (data.nameservers && data.nameservers.length > 0) {
                        nsHtml = `
                            <div class="alert alert-info text-start mt-3">
                                <h6><i class="bi bi-info-circle me-2"></i>Nameservers de Cloudflare:</h6>
                                ${data.nameservers.map(ns => `<code class="d-block">${ns}</code>`).join('')}
                                <small class="text-muted d-block mt-2">Configura estos NS en tu registrador actual (si el dominio ya existe en otro registrador).</small>
                            </div>
                        `;
                    }

                    let successTitle = hostingType === 'musedock_hosting'
                        ? 'Dominio y Hosting Configurados!'
                        : 'Dominio Registrado!';

                    let successMsg = hostingType === 'musedock_hosting'
                        ? `<p>Tu dominio <strong>${data.domain}</strong> ha sido registrado y tu sitio web esta listo.</p>`
                        : `<p>Tu dominio <strong>${data.domain}</strong> ha sido registrado. La zona DNS esta vacia y lista para configurar.</p>`;

                    Swal.fire({
                        icon: 'success',
                        title: successTitle,
                        html: `
                            ${successMsg}
                            ${nsHtml}
                        `,
                        confirmButtonColor: '#28a745',
                        confirmButtonText: 'Ir al Dashboard'
                    }).then(() => {
                        window.location.href = data.redirect || '/customer/dashboard';
                    });
                } else {
                    document.getElementById('registerBtn').disabled = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en el Registro',
                        html: `<p>${data.error || 'Ocurrio un error al registrar el dominio.'}</p><p class="small text-muted">Por favor intenta de nuevo o contacta soporte.</p>`,
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                clearInterval(interval);
                console.error('Error:', error);
                document.getElementById('registerBtn').disabled = false;
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexion',
                    text: 'No se pudo completar el registro. Por favor intenta de nuevo.',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}
</script>
@endsection
