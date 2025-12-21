@extends('Customer.layout')

@section('styles')
<style>
    .contact-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .contact-card .card-header {
        background: #17a2b8;
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .domain-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 10px;
    }
    .existing-contact {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .existing-contact:hover {
        border-color: #17a2b8;
        background: #f8f9ff;
    }
    .existing-contact.selected {
        border-color: #17a2b8;
        background: #e3f8fc;
    }
    .existing-contact .contact-name {
        font-weight: bold;
        font-size: 1.1rem;
    }
    .section-divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 30px 0;
    }
    .section-divider::before,
    .section-divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #e0e0e0;
    }
    .section-divider span {
        padding: 0 20px;
        color: #6c757d;
        font-size: 0.9rem;
    }
    .form-floating label {
        color: #6c757d;
    }
    .btn-submit {
        background: #17a2b8;
        border: none;
        padding: 12px 30px;
    }
    .btn-submit:hover {
        background: #138496;
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card contact-card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Datos del Registrante</h3>
                <div class="domain-badge">
                    <i class="bi bi-globe me-1"></i><?= htmlspecialchars($selectedDomain ?? '') ?>
                </div>
            </div>
            <div class="card-body p-4">

                <!-- Contactos existentes -->
                <?php if (!empty($contacts)): ?>
                <h5 class="mb-3"><i class="bi bi-people me-2"></i>Usar contacto existente</h5>

                <div class="existing-contacts mb-4">
                    <?php foreach ($contacts as $contact): ?>
                    <div class="existing-contact" onclick="selectExistingContact(<?= $contact['id'] ?>, this)">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="contact-name">
                                    <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                </div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($contact['email']) ?>
                                    <?php if ($contact['company']): ?>
                                    <br><?= htmlspecialchars($contact['company']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($contact['is_default']): ?>
                                <span class="badge bg-primary">Por defecto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-divider">
                    <span>o crear nuevo contacto</span>
                </div>
                <?php endif; ?>

                <!-- Formulario nuevo contacto -->
                <form id="contactForm" onsubmit="saveContact(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

                    <div class="row g-3">
                        <!-- Nombre y Apellido -->
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       placeholder="Nombre" required>
                                <label for="first_name">Nombre *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       placeholder="Apellidos" required>
                                <label for="last_name">Apellidos *</label>
                            </div>
                        </div>

                        <!-- Empresa (opcional) -->
                        <div class="col-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="company" name="company"
                                       placeholder="Empresa">
                                <label for="company">Empresa (opcional)</label>
                            </div>
                        </div>

                        <!-- Email y Telefono -->
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="Email" required
                                       value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                <label for="email">Email *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       placeholder="Telefono" required
                                       pattern="[\+]?[0-9\s\-\(\)]{9,20}">
                                <label for="phone">Telefono * (ej: +34 612345678)</label>
                            </div>
                        </div>

                        <!-- Direccion -->
                        <div class="col-md-9">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address_street" name="address_street"
                                       placeholder="Direccion" required>
                                <label for="address_street">Direccion *</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address_number" name="address_number"
                                       placeholder="Numero">
                                <label for="address_number">Numero</label>
                            </div>
                        </div>

                        <!-- Ciudad y Codigo Postal -->
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address_city" name="address_city"
                                       placeholder="Ciudad" required>
                                <label for="address_city">Ciudad *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address_zipcode" name="address_zipcode"
                                       placeholder="Codigo Postal" required>
                                <label for="address_zipcode">Codigo Postal *</label>
                            </div>
                        </div>

                        <!-- Provincia y Pais -->
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address_state" name="address_state"
                                       placeholder="Provincia/Estado">
                                <label for="address_state">Provincia/Estado</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="address_country" name="address_country" required>
                                    <option value="">Seleccionar pais...</option>
                                    <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= $code === 'ES' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="address_country">Pais *</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-submit btn-lg" id="submitBtn">
                            <i class="bi bi-arrow-right-circle me-2"></i>Continuar al Checkout
                        </button>
                        <a href="/customer/register-domain" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Volver a busqueda
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
let selectedContactId = null;

function selectExistingContact(contactId, element) {
    // Deseleccionar otros
    document.querySelectorAll('.existing-contact').forEach(el => {
        el.classList.remove('selected');
    });

    // Seleccionar este
    element.classList.add('selected');
    selectedContactId = contactId;

    // Confirmar seleccion
    Swal.fire({
        title: 'Usar este contacto?',
        text: 'Se usaran estos datos para el registro del dominio',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviar seleccion
            const formData = new FormData();
            formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');
            formData.append('contact_id', contactId);

            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('/customer/domain/contact/select', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || '/customer/register-domain/checkout';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al seleccionar contacto'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexion'
                });
            });
        } else {
            element.classList.remove('selected');
            selectedContactId = null;
        }
    });
}

function saveContact(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // Validacion basica
    const required = ['first_name', 'last_name', 'email', 'phone', 'address_street', 'address_city', 'address_zipcode', 'address_country'];
    for (const field of required) {
        if (!formData.get(field)) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: 'Por favor completa todos los campos obligatorios'
            });
            return;
        }
    }

    Swal.fire({
        title: 'Guardando contacto...',
        html: 'Creando registro en OpenProvider...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    document.getElementById('submitBtn').disabled = true;

    fetch('/customer/domain/contact/save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Contacto guardado',
                text: 'Redirigiendo al checkout...',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = data.redirect || '/customer/register-domain/checkout';
            });
        } else {
            document.getElementById('submitBtn').disabled = false;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al guardar contacto'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('submitBtn').disabled = false;
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexion. Intenta de nuevo.'
        });
    });
}
</script>
@endsection
