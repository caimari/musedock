<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Solicitar Subdominio FREE' ?> - MuseDock</title>
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
            max-width: 600px;
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

        .subdomain-input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .subdomain-input-group input {
            padding-right: 150px;
            font-size: 16px;
            height: 50px;
        }

        .subdomain-suffix {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-weight: 500;
            pointer-events: none;
        }

        .availability-status {
            font-size: 14px;
            margin-top: 8px;
            min-height: 20px;
        }

        .availability-status.checking {
            color: #6c757d;
        }

        .availability-status.available {
            color: #28a745;
            font-weight: 500;
        }

        .availability-status.unavailable {
            color: #dc3545;
            font-weight: 500;
        }

        .btn-create {
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

        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-create:disabled {
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
    </style>
</head>
<body>
    <div class="request-card">
        <div class="card-header-custom">
            <h1><i class="fas fa-globe"></i> Solicitar Subdominio FREE</h1>
            <p>Crea tu sitio web con un subdominio gratuito de MuseDock</p>
        </div>

        <div class="card-body-custom">
            <div class="info-box">
                <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
                <ul>
                    <li>Solo puedes tener <strong>1 subdominio FREE</strong> activo a la vez</li>
                    <li>El subdominio debe tener entre 3 y 30 caracteres</li>
                    <li>Solo letras minúsculas, números y guiones</li>
                    <li>La configuración es automática y tarda aproximadamente 2 minutos</li>
                </ul>
            </div>

            <form id="freeSubdomainForm" method="POST" action="/customer/request-free-subdomain">
                <input type="hidden" name="_csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-4">
                    <label for="subdomain" class="form-label fw-bold">
                        <i class="fas fa-tag"></i> Elige tu Subdominio
                    </label>
                    <div class="subdomain-input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="subdomain"
                            name="subdomain"
                            placeholder="mi-empresa"
                            required
                            pattern="[a-z0-9-]{3,30}"
                            autocomplete="off"
                        >
                        <span class="subdomain-suffix">.musedock.com</span>
                    </div>
                    <div id="availabilityStatus" class="availability-status"></div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-lightbulb"></i>
                        Ejemplo: <code>mi-empresa</code> → <strong>mi-empresa.musedock.com</strong>
                    </small>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-create" disabled>
                    <i class="fas fa-rocket"></i> Crear Mi Subdominio FREE
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
        const subdomainInput = document.getElementById('subdomain');
        const availabilityStatus = document.getElementById('availabilityStatus');
        const submitBtn = document.getElementById('submitBtn');
        let checkTimeout;

        // Validación en tiempo real y verificación de disponibilidad
        subdomainInput.addEventListener('input', function() {
            let value = this.value.toLowerCase();

            // Forzar minúsculas
            this.value = value;

            // Limpiar timeout anterior
            clearTimeout(checkTimeout);

            // Validar formato
            if (value.length === 0) {
                availabilityStatus.textContent = '';
                availabilityStatus.className = 'availability-status';
                submitBtn.disabled = true;
                return;
            }

            if (value.length < 3) {
                availabilityStatus.textContent = '⚠️ Mínimo 3 caracteres';
                availabilityStatus.className = 'availability-status unavailable';
                submitBtn.disabled = true;
                return;
            }

            if (!/^[a-z0-9-]+$/.test(value)) {
                availabilityStatus.textContent = '❌ Solo letras minúsculas, números y guiones';
                availabilityStatus.className = 'availability-status unavailable';
                submitBtn.disabled = true;
                return;
            }

            // Verificar disponibilidad después de 500ms
            availabilityStatus.textContent = '🔍 Verificando disponibilidad...';
            availabilityStatus.className = 'availability-status checking';
            submitBtn.disabled = true;

            checkTimeout = setTimeout(() => {
                checkAvailability(value);
            }, 500);
        });

        function checkAvailability(subdomain) {
            fetch(`/customer/check-free-subdomain?subdomain=${encodeURIComponent(subdomain)}`)
                .then(response => response.json())
                .then(data => {
                    availabilityStatus.textContent = data.message;
                    availabilityStatus.className = `availability-status ${data.available ? 'available' : 'unavailable'}`;
                    submitBtn.disabled = !data.available;
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                    availabilityStatus.textContent = '⚠️ Error al verificar disponibilidad';
                    availabilityStatus.className = 'availability-status unavailable';
                    submitBtn.disabled = true;
                });
        }

        // Submit del formulario
        document.getElementById('freeSubdomainForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const subdomain = subdomainInput.value.trim();

            if (!subdomain || subdomain.length < 3) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor ingresa un subdominio válido'
                });
                return;
            }

            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando tu subdominio...';

            const formData = new FormData(this);

            fetch('/customer/request-free-subdomain', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Subdominio Creado!',
                        html: `
                            <p><strong>${data.domain}</strong></p>
                            <p class="text-muted mb-0">Tu sitio web está siendo configurado automáticamente.</p>
                            <p class="text-muted">Recibirás un correo electrónico cuando esté listo (aprox. 2 minutos).</p>
                        `,
                        confirmButtonText: 'Ir al Dashboard',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '/customer/dashboard';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al crear el subdominio'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Crear Mi Subdominio FREE';
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
                submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Crear Mi Subdominio FREE';
            });
        });
    </script>
</body>
</html>
