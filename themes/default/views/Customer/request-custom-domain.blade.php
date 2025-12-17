@extends('Customer.layout')

@section('styles')
<style>
    .request-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .request-card .card-header {
        background: linear-gradient(135deg, #a8b5f5, #c4a6e0);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 20px;
    }
    .info-box {
        background: #f8f9fa;
        border-left: 4px solid #667eea;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        padding: 12px 30px;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd6, #6a4292);
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card request-card">
            <div class="card-header text-center">
                <h4 class="mb-0" style="font-size: 1.4rem; font-weight: 700;"><i class="bi bi-globe me-2"></i>Incorporar Dominio Personalizado</h4>
                <p class="mb-0 mt-2 opacity-90" style="font-size: 0.9rem;">Conecta tu propio dominio a MuseDock</p>
            </div>
            <div class="card-body p-4">
                <div class="info-box">
                    <h6><i class="bi bi-info-circle me-2"></i>Como funciona</h6>
                    <ul class="mb-0 ps-3">
                        <li>Tu dominio sera protegido por Cloudflare automaticamente</li>
                        <li>Recibiras instrucciones para cambiar los nameservers</li>
                        <li>Tu sitio se activara automaticamente cuando detectemos el cambio</li>
                        <li>Opcionalmente puedes habilitar Email Routing para recibir correos</li>
                    </ul>
                </div>

                <div class="warning-box">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Importante</h6>
                    <p class="mb-0">Debes ser el propietario del dominio y tener acceso para cambiar los nameservers en tu registrador (GoDaddy, Namecheap, etc.)</p>
                </div>

                <form id="customDomainForm" onsubmit="submitRequest(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tu Dominio</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-globe"></i></span>
                            <input type="text" class="form-control" name="domain"
                                   placeholder="tudominio.com"
                                   pattern="^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}$"
                                   required>
                        </div>
                        <div class="form-text">Introduce tu dominio sin www (ejemplo: miempresa.com)</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enable_email_routing" id="enableEmailRouting">
                            <label class="form-check-label" for="enableEmailRouting">
                                <strong>Habilitar Email Routing</strong>
                                <br><small class="text-muted">Los correos enviados a cualquier direccion de tu dominio seran redirigidos a <?= htmlspecialchars($customer['email'] ?? '') ?></small>
                            </label>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-rocket-takeoff me-2"></i>Incorporar Dominio
                        </button>
                        <a href="/customer/dashboard" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Volver al Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function submitRequest(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const domain = formData.get('domain').toLowerCase().trim();

    // Validar dominio
    if (!domain || domain.includes('musedock.com')) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Para subdominios de musedock.com usa "Solicitar Subdominio FREE"'
        });
        return;
    }

    Swal.fire({
        title: 'Procesando solicitud...',
        html: 'Estamos configurando tu dominio en Cloudflare...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/customer/request-custom-domain', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let nsHtml = '<div class="text-start mt-3">';
            nsHtml += '<p><strong>Cambia los nameservers de tu dominio a:</strong></p>';
            nsHtml += '<div class="bg-light p-3 rounded">';
            data.nameservers.forEach((ns, i) => {
                nsHtml += `<p class="mb-1"><code>NS${i+1}: ${ns}</code></p>`;
            });
            nsHtml += '</div>';
            nsHtml += '<p class="mt-3 text-muted small">Te hemos enviado un email con instrucciones detalladas.</p>';
            nsHtml += '</div>';

            Swal.fire({
                icon: 'success',
                title: 'Dominio Registrado!',
                html: data.message + nsHtml,
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Ir al Dashboard'
            }).then(() => {
                window.location.href = '/customer/dashboard';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Ocurrio un error al procesar la solicitud'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexion. Por favor intenta de nuevo.'
        });
    });
}
</script>
@endsection
