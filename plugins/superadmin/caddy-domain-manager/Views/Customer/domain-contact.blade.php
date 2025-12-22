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
    .contact-section {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        background: #f8f9fa;
    }
    .contact-section.collapsed .contact-fields {
        display: none;
    }
    .contact-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    }
    .contact-section-header h5 {
        margin: 0;
        color: #17a2b8;
    }
    .form-floating label {
        color: #6c757d;
    }
    .options-section {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
    }
    .options-section h5 {
        color: #17a2b8;
        margin-bottom: 15px;
    }
    .option-card {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .option-card:hover {
        border-color: #17a2b8;
    }
    .option-card.selected {
        border-color: #17a2b8;
        background: #e3f8fc;
    }
    .option-card input[type="radio"] {
        display: none;
    }
    .option-card .option-title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    .option-card .option-desc {
        font-size: 0.9rem;
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
    .existing-contact {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .existing-contact:hover, .existing-contact.selected {
        border-color: #17a2b8;
        background: #e3f8fc;
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
    .step.active {
        color: #17a2b8;
        font-weight: bold;
    }
    .step-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e0e0e0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
    }
    .step.active .step-number {
        background: #17a2b8;
        color: white;
    }
    .step-line {
        width: 50px;
        height: 2px;
        background: #e0e0e0;
        margin: 0 15px;
    }
    .ns-input-group {
        margin-bottom: 10px;
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Step indicator -->
        <div class="step-indicator">
            <div class="step">
                <span class="step-number">1</span>
                <span>Buscar</span>
            </div>
            <div class="step-line"></div>
            <div class="step active">
                <span class="step-number">2</span>
                <span>Datos y Opciones</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <span class="step-number">3</span>
                <span>Confirmar</span>
            </div>
        </div>

        <div class="card contact-card">
            <div class="card-header">
                <h3 class="mb-0 text-white"><i class="bi bi-person-vcard me-2 text-white"></i>Registro de Dominio</h3>
                <div class="domain-badge">
                    <i class="bi bi-globe me-1"></i><?= htmlspecialchars($selectedDomain ?? '') ?>
                    <span class="ms-2">- <?= number_format($selectedPrice ?? 0, 2) ?> <?= htmlspecialchars($selectedCurrency ?? 'USD') ?>/ano</span>
                </div>
            </div>
            <div class="card-body p-4">
                <form id="registrationForm" onsubmit="submitRegistration(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

                    <!-- SECCION 1: Contacto Propietario (Owner) -->
                    <div class="contact-section" id="ownerSection">
                        <div class="contact-section-header mb-3">
                            <h5><i class="bi bi-person-fill me-2"></i>Contacto Propietario (Owner)</h5>
                        </div>

                        <?php if (!empty($contacts)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Seleccionar contacto existente:</label>
                            <select class="form-select" id="owner_existing" name="owner_existing" onchange="toggleOwnerForm(this.value)">
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

                        <div class="contact-fields" id="ownerFields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_first_name" name="owner_first_name" placeholder="Nombre">
                                        <label>Nombre *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_last_name" name="owner_last_name" placeholder="Apellidos">
                                        <label>Apellidos *</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_company" name="owner_company" placeholder="Empresa">
                                        <label>Empresa (opcional)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="owner_email" name="owner_email" placeholder="Email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                        <label>Email *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="owner_phone" name="owner_phone" placeholder="Telefono">
                                        <label>Telefono * (ej: +34612345678)</label>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_street" name="owner_street" placeholder="Direccion">
                                        <label>Direccion *</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_number" name="owner_number" placeholder="Numero">
                                        <label>Numero</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_city" name="owner_city" placeholder="Ciudad">
                                        <label>Ciudad *</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_zipcode" name="owner_zipcode" placeholder="CP">
                                        <label>Codigo Postal *</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="owner_country" name="owner_country">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?= $code ?>" <?= $code === 'ES' ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Pais *</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checkbox usar mismo contacto -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="same_contact_all" name="same_contact_all" value="1" checked onchange="toggleOtherContacts()">
                        <label class="form-check-label fw-bold" for="same_contact_all">
                            Usar el mismo contacto para Admin, Tecnico y Facturacion
                        </label>
                    </div>

                    <!-- SECCION 2-4: Otros contactos (ocultos por defecto) -->
                    <div id="otherContactsContainer" style="display: none;">
                        <!-- Admin Contact -->
                        <div class="contact-section">
                            <div class="contact-section-header mb-3">
                                <h5><i class="bi bi-person-gear me-2"></i>Contacto Administrativo</h5>
                            </div>
                            <div class="contact-fields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="admin_first_name" placeholder="Nombre">
                                            <label>Nombre</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="admin_last_name" placeholder="Apellidos">
                                            <label>Apellidos</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" name="admin_email" placeholder="Email">
                                            <label>Email</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="tel" class="form-control" name="admin_phone" placeholder="Telefono">
                                            <label>Telefono</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tech Contact -->
                        <div class="contact-section">
                            <div class="contact-section-header mb-3">
                                <h5><i class="bi bi-tools me-2"></i>Contacto Tecnico</h5>
                            </div>
                            <div class="contact-fields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="tech_first_name" placeholder="Nombre">
                                            <label>Nombre</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="tech_last_name" placeholder="Apellidos">
                                            <label>Apellidos</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" name="tech_email" placeholder="Email">
                                            <label>Email</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="tel" class="form-control" name="tech_phone" placeholder="Telefono">
                                            <label>Telefono</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Billing Contact -->
                        <div class="contact-section">
                            <div class="contact-section-header mb-3">
                                <h5><i class="bi bi-credit-card me-2"></i>Contacto de Facturacion</h5>
                            </div>
                            <div class="contact-fields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="billing_first_name" placeholder="Nombre">
                                            <label>Nombre</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="billing_last_name" placeholder="Apellidos">
                                            <label>Apellidos</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" name="billing_email" placeholder="Email">
                                            <label>Email</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="tel" class="form-control" name="billing_phone" placeholder="Telefono">
                                            <label>Telefono</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCION: Opciones de Hosting/DNS -->
                    <div class="options-section">
                        <h5><i class="bi bi-hdd-network me-2"></i>Tipo de Servicio</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="option-card selected" onclick="selectOption('hosting_type', 'musedock_hosting', this)">
                                    <input type="radio" name="hosting_type" value="musedock_hosting" checked>
                                    <div class="option-title"><i class="bi bi-cloud-check me-2"></i>DNS + Hosting MuseDock</div>
                                    <div class="option-desc">
                                        Tu dominio apuntara a tu sitio en MuseDock con SSL incluido.
                                        <br><small class="text-success">Recomendado</small>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="option-card" onclick="selectOption('hosting_type', 'dns_only', this)">
                                    <input type="radio" name="hosting_type" value="dns_only">
                                    <div class="option-title"><i class="bi bi-diagram-3 me-2"></i>Solo Gestion DNS</div>
                                    <div class="option-desc">
                                        Gestionaras tus DNS libremente via Cloudflare.
                                        <br><small>Sin registros predefinidos</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SECCION: Nameservers -->
                    <div class="options-section">
                        <h5><i class="bi bi-server me-2"></i>Nameservers</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="option-card selected" onclick="selectOption('ns_type', 'cloudflare', this); toggleCustomNS(false)">
                                    <input type="radio" name="ns_type" value="cloudflare" checked>
                                    <div class="option-title"><i class="bi bi-shield-check me-2"></i>Cloudflare (Recomendado)</div>
                                    <div class="option-desc">
                                        Usaras los nameservers de Cloudflare para proteccion y velocidad.
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="option-card" onclick="selectOption('ns_type', 'custom', this); toggleCustomNS(true)">
                                    <input type="radio" name="ns_type" value="custom">
                                    <div class="option-title"><i class="bi bi-pencil-square me-2"></i>Personalizados</div>
                                    <div class="option-desc">
                                        Usaras tus propios nameservers.
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="customNsContainer" style="display: none;">
                            <label class="form-label">Ingresa tus nameservers:</label>
                            <div class="ns-input-group">
                                <input type="text" class="form-control" name="custom_ns[]" placeholder="ns1.tudominio.com">
                            </div>
                            <div class="ns-input-group">
                                <input type="text" class="form-control" name="custom_ns[]" placeholder="ns2.tudominio.com">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addNsField()">
                                <i class="bi bi-plus"></i> Agregar otro NS
                            </button>
                        </div>
                    </div>

                    <!-- Botones -->
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
function toggleOwnerForm(value) {
    const fields = document.getElementById('ownerFields');
    if (value) {
        fields.style.display = 'none';
    } else {
        fields.style.display = 'block';
    }
}

function toggleOtherContacts() {
    const checkbox = document.getElementById('same_contact_all');
    const container = document.getElementById('otherContactsContainer');
    container.style.display = checkbox.checked ? 'none' : 'block';
}

function selectOption(name, value, element) {
    // Deseleccionar hermanos
    element.parentElement.parentElement.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('selected');
    });
    // Seleccionar este
    element.classList.add('selected');
    element.querySelector('input').checked = true;
}

function toggleCustomNS(show) {
    document.getElementById('customNsContainer').style.display = show ? 'block' : 'none';
}

function addNsField() {
    const container = document.getElementById('customNsContainer');
    const count = container.querySelectorAll('.ns-input-group').length;
    if (count >= 5) {
        Swal.fire('Limite', 'Maximo 5 nameservers permitidos', 'warning');
        return;
    }

    const div = document.createElement('div');
    div.className = 'ns-input-group';
    div.innerHTML = '<input type="text" class="form-control" name="custom_ns[]" placeholder="ns' + (count + 1) + '.tudominio.com">';
    container.insertBefore(div, container.lastElementChild);
}

function submitRegistration(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // Validar contacto owner si es nuevo
    const ownerExisting = document.getElementById('owner_existing');
    if (!ownerExisting || !ownerExisting.value) {
        const required = ['owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone', 'owner_street', 'owner_city', 'owner_zipcode', 'owner_country'];
        for (const field of required) {
            if (!formData.get(field)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Por favor completa todos los campos del contacto propietario'
                });
                document.getElementById(field)?.focus();
                return;
            }
        }
    }

    // Validar NS personalizados si se eligieron
    if (formData.get('ns_type') === 'custom') {
        const customNs = formData.getAll('custom_ns[]').filter(ns => ns.trim() !== '');
        if (customNs.length < 2) {
            Swal.fire({
                icon: 'warning',
                title: 'Nameservers requeridos',
                text: 'Debes ingresar al menos 2 nameservers personalizados'
            });
            return;
        }
    }

    Swal.fire({
        title: 'Procesando...',
        html: 'Guardando datos y preparando checkout...',
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
                title: 'Datos guardados',
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
                text: data.error || 'Error al guardar datos'
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
