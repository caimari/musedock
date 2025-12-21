@extends('Customer.layout')

@section('styles')
<style>
    .checkout-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .checkout-card .card-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        padding: 15px 40px;
        font-size: 1.1rem;
        border-radius: 30px;
    }
    .btn-register:hover {
        background: linear-gradient(135deg, #218838 0%, #1a9d7d 100%);
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
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card checkout-card">
            <div class="card-header">
                <i class="bi bi-bag-check" style="font-size: 2.5rem;"></i>
                <h3 class="mb-0 mt-2">Confirmar Registro</h3>
                <div class="domain-display"><?= htmlspecialchars($domain ?? '') ?></div>
            </div>
            <div class="card-body p-4">

                <div class="row">
                    <!-- Resumen del pedido -->
                    <div class="col-md-7">
                        <div class="summary-section">
                            <h6><i class="bi bi-receipt me-2"></i>Resumen del Pedido</h6>

                            <div class="summary-row">
                                <span>Dominio</span>
                                <strong><?= htmlspecialchars($domain ?? '') ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Periodo</span>
                                <span>1 ano</span>
                            </div>
                            <div class="summary-row">
                                <span>Proteccion Cloudflare</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>
                            <div class="summary-row">
                                <span>SSL/HTTPS</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>
                            <div class="summary-row">
                                <span>Email Routing</span>
                                <span class="text-success"><i class="bi bi-check-circle"></i> Incluido</span>
                            </div>

                            <div class="summary-row total">
                                <span>Total</span>
                                <span><?= number_format($price ?? 0, 2) ?> <?= htmlspecialchars($currency ?? 'EUR') ?></span>
                            </div>
                        </div>

                        <!-- Datos del registrante -->
                        <div class="summary-section">
                            <h6><i class="bi bi-person me-2"></i>Datos del Registrante</h6>
                            <div class="contact-info">
                                <strong><?= htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?></strong>
                                <?php if (!empty($contact['company'])): ?>
                                <br><?= htmlspecialchars($contact['company']) ?>
                                <?php endif; ?>
                                <br><?= htmlspecialchars($contact['email'] ?? '') ?>
                                <br><?= htmlspecialchars($contact['phone'] ?? '') ?>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars($contact['address_street'] ?? '') ?>
                                    <?= htmlspecialchars($contact['address_number'] ?? '') ?>,
                                    <?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>
                                    <?= htmlspecialchars($contact['address_city'] ?? '') ?>
                                    (<?= htmlspecialchars($contact['address_country'] ?? '') ?>)
                                </small>
                            </div>
                            <a href="/customer/register-domain/contact" class="btn btn-sm btn-outline-secondary mt-3">
                                <i class="bi bi-pencil me-1"></i>Cambiar contacto
                            </a>
                        </div>
                    </div>

                    <!-- Que incluye -->
                    <div class="col-md-5">
                        <div class="summary-section h-100">
                            <h6><i class="bi bi-gift me-2"></i>Tu Registro Incluye</h6>
                            <ul class="feature-list">
                                <li><i class="bi bi-check-circle-fill"></i>Registro por 1 ano</li>
                                <li><i class="bi bi-check-circle-fill"></i>Renovacion automatica</li>
                                <li><i class="bi bi-check-circle-fill"></i>DNS gestionado por Cloudflare</li>
                                <li><i class="bi bi-check-circle-fill"></i>Certificado SSL gratuito</li>
                                <li><i class="bi bi-check-circle-fill"></i>Proteccion DDoS</li>
                                <li><i class="bi bi-check-circle-fill"></i>CDN Global</li>
                                <li><i class="bi bi-check-circle-fill"></i>Email Routing configurado</li>
                                <li><i class="bi bi-check-circle-fill"></i>Panel de administracion</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="info-box">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Informacion Importante</h6>
                    <p class="mb-0 small">
                        Al completar el registro, el dominio sera configurado automaticamente con los DNS de Cloudflare.
                        Recibiras un email con instrucciones para activar tu sitio web.
                    </p>
                </div>

                <!-- Botones -->
                <div class="d-grid gap-2 mt-4">
                    <button type="button" class="btn btn-success btn-register btn-lg" id="registerBtn" onclick="confirmRegistration()">
                        <i class="bi bi-check2-circle me-2"></i>Confirmar Registro
                    </button>
                    <a href="/customer/register-domain" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Cancelar y volver
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function confirmRegistration() {
    Swal.fire({
        title: 'Confirmar Registro',
        html: `
            <p>Vas a registrar el dominio:</p>
            <h4 class="text-primary"><?= htmlspecialchars($domain ?? '') ?></h4>
            <p class="mb-0">Total: <strong><?= number_format($price ?? 0, 2) ?> <?= htmlspecialchars($currency ?? 'EUR') ?></strong></p>
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

    Swal.fire({
        title: 'Registrando dominio...',
        html: `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p id="statusText">Iniciando registro...</p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            // Actualizar texto de estado
            const statusText = document.getElementById('statusText');
            let step = 0;
            const steps = [
                'Conectando con OpenProvider...',
                'Registrando dominio...',
                'Configurando DNS en Cloudflare...',
                'Creando tu sitio web...',
                'Finalizando...'
            ];

            const interval = setInterval(() => {
                step++;
                if (step < steps.length) {
                    statusText.textContent = steps[step];
                }
            }, 2000);

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
                                <small class="text-muted d-block mt-2">Configura estos NS en tu registrador si es necesario.</small>
                            </div>
                        `;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Dominio Registrado!',
                        html: `
                            <p>Tu dominio <strong>${data.domain}</strong> ha sido registrado exitosamente.</p>
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
                        text: data.error || 'Ocurrio un error al registrar el dominio. Por favor intenta de nuevo.',
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
