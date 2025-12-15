<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Incorporar Dominio Personalizado' ?> - MuseDock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .request-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 700px;
            width: 100%;
            margin: 20px;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header-custom h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .card-header-custom p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body-custom {
            padding: 40px;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .info-box h5 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            color: #666;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .warning-box h5 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .warning-box p {
            margin: 0;
            font-size: 14px;
            color: #856404;
        }

        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .success-box h5 {
            color: #155724;
            font-size: 16px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .success-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            color: #155724;
        }

        .domain-input {
            font-size: 18px;
            height: 55px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .domain-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            color: #667eea;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-back:hover {
            color: #764ba2;
            text-decoration: none;
        }

        .form-check-label {
            font-size: 15px;
            color: #555;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: -1;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: #e0e0e0;
            color: #999;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .step.active .step-number {
            background: #667eea;
            color: white;
        }

        .step-text {
            font-size: 12px;
            color: #999;
        }

        .step.active .step-text {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="request-card">
        <div class="card-header-custom">
            <h1><i class="fas fa-crown"></i> Incorporar Dominio Personalizado</h1>
            <p>Usa tu propio dominio con toda la potencia de MuseDock</p>
        </div>

        <div class="card-body-custom">
            <!-- Indicador de pasos -->
            <div class="step-indicator">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-text">Solicitar</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">Cambiar NS</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">Activación</div>
                </div>
            </div>

            <div class="success-box">
                <h5><i class="fas fa-check-circle"></i> Incluye</h5>
                <ul>
                    <li><strong>SSL Gratis</strong> - Certificado HTTPS automático</li>
                    <li><strong>Cloudflare Protection</strong> - DDoS protection y CDN global</li>
                    <li><strong>Email Routing</strong> - Reenvío de correos a tu email (opcional)</li>
                    <li><strong>DNS Management</strong> - Gestiona tus registros DNS desde el dashboard</li>
                </ul>
            </div>

            <div class="info-box">
                <h5><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h5>
                <ul>
                    <li>Ingresa tu dominio existente (debe estar registrado en otro proveedor)</li>
                    <li>Añadiremos tu dominio a Cloudflare automáticamente</li>
                    <li>Recibirás instrucciones por email para cambiar los nameservers</li>
                    <li>Cambias los NS en tu proveedor actual (GoDaddy, Namecheap, etc.)</li>
                    <li>Nuestro sistema detecta el cambio y activa tu sitio automáticamente</li>
                </ul>
            </div>

            <div class="warning-box">
                <h5><i class="fas fa-exclamation-triangle"></i> Importante</h5>
                <p>El cambio de nameservers puede tardar entre <strong>2 y 48 horas</strong> en propagarse. Durante ese tiempo, tu dominio puede estar temporalmente inaccesible.</p>
            </div>

            <form id="customDomainForm" method="POST" action="/customer/request-custom-domain">
                <input type="hidden" name="_csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-4">
                    <label for="domain" class="form-label fw-bold">
                        <i class="fas fa-globe"></i> Tu Dominio
                    </label>
                    <input
                        type="text"
                        class="form-control domain-input"
                        id="domain"
                        name="domain"
                        placeholder="ejemplo.com"
                        required
                        autocomplete="off"
                    >
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-lightbulb"></i>
                        Ingresa solo el dominio, sin <code>www</code> ni <code>https://</code>
                    </small>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="enable_email_routing"
                            name="enable_email_routing"
                            checked
                        >
                        <label class="form-check-label" for="enable_email_routing">
                            <i class="fas fa-envelope"></i> Habilitar Email Routing
                            <small class="d-block text-muted">
                                Reenviar todos los emails de tu dominio a <strong><?= htmlspecialchars($customer['email']) ?></strong>
                            </small>
                        </label>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-submit">
                    <i class="fas fa-rocket"></i> Incorporar Mi Dominio
                </button>
            </form>

            <div class="text-center">
                <a href="/customer/dashboard" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        const domainInput = document.getElementById('domain');

        // Limpiar input de dominio
        domainInput.addEventListener('input', function() {
            let value = this.value.toLowerCase().trim();

            // Remover protocolo si lo ingresaron
            value = value.replace(/^https?:\/\//, '');

            // Remover www. si lo ingresaron
            value = value.replace(/^www\./, '');

            // Remover trailing slash
            value = value.replace(/\/$/, '');

            this.value = value;
        });

        // Submit del formulario
        document.getElementById('customDomainForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const domain = domainInput.value.trim();

            if (!domain || domain.length < 3) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor ingresa un dominio válido'
                });
                return;
            }

            // Validar formato básico
            if (!/^[a-z0-9.-]+\.[a-z]{2,}$/.test(domain)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Formato Inválido',
                    text: 'El dominio debe tener un formato válido (ej: ejemplo.com)'
                });
                return;
            }

            // Verificar que no sea musedock.com
            if (domain.includes('musedock.com')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Dominio No Permitido',
                    html: 'Para subdominios de <strong>musedock.com</strong> usa la opción <br>"<strong>Solicitar Subdominio FREE</strong>"'
                });
                return;
            }

            // Confirmar antes de enviar
            Swal.fire({
                title: '¿Confirmar incorporación?',
                html: `
                    <p>Vas a incorporar el dominio:</p>
                    <p class="fs-5 fw-bold text-primary">${domain}</p>
                    <p class="text-muted small">Recibirás instrucciones por email para cambiar los nameservers.</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, incorporar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    submitForm();
                }
            });
        });

        function submitForm() {
            const submitBtn = document.getElementById('submitBtn');

            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            const formData = new FormData(document.getElementById('customDomainForm'));

            fetch('/customer/request-custom-domain', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Dominio Añadido!',
                        html: `
                            <p><strong>${data.domain}</strong></p>
                            <div class="alert alert-info mt-3 text-start">
                                <p class="mb-2"><strong>📧 Revisa tu email</strong></p>
                                <p class="mb-0 small">Te hemos enviado las instrucciones para cambiar los nameservers de tu dominio.</p>
                            </div>
                            <div class="alert alert-warning mt-2 text-start">
                                <p class="mb-0 small">
                                    <strong>⏱️ Tiempo de propagación:</strong> 2-48 horas<br>
                                    Nuestro sistema verificará automáticamente cada 30 minutos.
                                </p>
                            </div>
                        `,
                        confirmButtonText: 'Ir al Dashboard',
                        confirmButtonColor: '#667eea',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '/customer/dashboard';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al incorporar el dominio'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Incorporar Mi Dominio';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al procesar la solicitud. Por favor intenta de nuevo.'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Incorporar Mi Dominio';
            });
        }
    </script>
</body>
</html>
