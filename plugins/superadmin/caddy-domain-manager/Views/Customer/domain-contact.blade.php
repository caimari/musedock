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
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .domain-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        font-size: 0.95rem;
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
                    <span class="ms-2">- <?= number_format($selectedPrice ?? 0, 2) ?> <?= htmlspecialchars($selectedCurrency ?? 'USD') ?>/año</span>
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
                            <div class="input-group">
                                <select class="form-select" id="owner_existing" name="owner_existing" onchange="toggleOwnerForm(this.value)">
                                    <option value="">-- Crear nuevo contacto --</option>
                                    <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"
                                        data-firstname="<?= htmlspecialchars($contact['first_name'] ?? '') ?>"
                                        data-lastname="<?= htmlspecialchars($contact['last_name'] ?? '') ?>"
                                        data-company="<?= htmlspecialchars($contact['company'] ?? '') ?>"
                                        data-companyregnumber="<?= htmlspecialchars($contact['company_reg_number'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($contact['email'] ?? '') ?>"
                                        data-phone="<?= htmlspecialchars($contact['phone'] ?? '') ?>"
                                        data-phonecode="<?= htmlspecialchars($contact['phone_code'] ?? '34') ?>"
                                        data-street="<?= htmlspecialchars($contact['address_street'] ?? '') ?>"
                                        data-number="<?= htmlspecialchars($contact['address_number'] ?? '') ?>"
                                        data-city="<?= htmlspecialchars($contact['address_city'] ?? '') ?>"
                                        data-state="<?= htmlspecialchars($contact['address_state'] ?? '') ?>"
                                        data-zipcode="<?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>"
                                        data-country="<?= htmlspecialchars($contact['address_country'] ?? 'ES') ?>">
                                        <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        (<?= htmlspecialchars($contact['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="btnEditOwner" onclick="editSelectedContact()" style="display: none;" title="Editar contacto seleccionado">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                            </div>
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
                                <div class="col-md-8">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_company" name="owner_company" placeholder="Empresa" onchange="toggleCompanyRegNumber()">
                                        <label>Empresa (opcional)</label>
                                    </div>
                                </div>
                                <div class="col-md-4" id="owner_company_reg_container" style="display: none;">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_company_reg_number" name="owner_company_reg_number" placeholder="CIF/NIF">
                                        <label>CIF/NIF <span class="es-company-required">*</span></label>
                                    </div>
                                    <small class="text-muted es-company-hint"><i class="bi bi-info-circle"></i> Obligatorio para .ES si es empresa</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="owner_email" name="owner_email" placeholder="Email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                        <label>Email *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Telefono *</label>
                                    <div class="input-group">
                                        <select class="form-select" id="owner_phone_code" name="owner_phone_code" style="max-width: 110px;">
                                            <?php foreach ($phoneCodes as $code => $number): ?>
                                            <option value="<?= $number ?>" <?= $code === 'ES' ? 'selected' : '' ?>>+<?= $number ?> (<?= $code ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="tel" class="form-control" id="owner_phone" name="owner_phone" placeholder="612345678">
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
                                        <label>Numero *</label>
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
                                        <input type="text" class="form-control" id="owner_state" name="owner_state" placeholder="Provincia">
                                        <label>Provincia <span class="es-required">*</span></label>
                                    </div>
                                    <small class="text-muted es-hint" style="display:none;"><i class="bi bi-info-circle"></i> Obligatorio segun pais</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="owner_zipcode" name="owner_zipcode" placeholder="CP">
                                        <label>Codigo Postal *</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="owner_country" name="owner_country" onchange="toggleStateRequired()">
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
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyFromOwner('admin')">
                                    <i class="bi bi-files me-1"></i>Copiar del Propietario
                                </button>
                            </div>

                            <?php if (!empty($contacts)): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccionar contacto existente:</label>
                                <select class="form-select" id="admin_existing" name="admin_existing" onchange="toggleContactForm('admin', this.value)">
                                    <option value="">-- Crear nuevo contacto --</option>
                                    <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"
                                        data-firstname="<?= htmlspecialchars($contact['first_name'] ?? '') ?>"
                                        data-lastname="<?= htmlspecialchars($contact['last_name'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($contact['email'] ?? '') ?>"
                                        data-phone="<?= htmlspecialchars($contact['phone'] ?? '') ?>"
                                        data-phonecode="<?= htmlspecialchars($contact['phone_code'] ?? '34') ?>"
                                        data-street="<?= htmlspecialchars($contact['address_street'] ?? '') ?>"
                                        data-number="<?= htmlspecialchars($contact['address_number'] ?? '') ?>"
                                        data-city="<?= htmlspecialchars($contact['address_city'] ?? '') ?>"
                                        data-state="<?= htmlspecialchars($contact['address_state'] ?? '') ?>"
                                        data-zipcode="<?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>"
                                        data-country="<?= htmlspecialchars($contact['address_country'] ?? 'ES') ?>">
                                        <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        (<?= htmlspecialchars($contact['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="contact-fields" id="adminFields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_first_name" name="admin_first_name" placeholder="Nombre">
                                            <label>Nombre *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_last_name" name="admin_last_name" placeholder="Apellidos">
                                            <label>Apellidos *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="admin_email" name="admin_email" placeholder="Email">
                                            <label>Email *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Telefono *</label>
                                        <div class="input-group">
                                            <select class="form-select" id="admin_phone_code" name="admin_phone_code" style="max-width: 110px;">
                                                <?php foreach ($phoneCodes as $code => $number): ?>
                                                <option value="<?= $number ?>" <?= $code === 'ES' ? 'selected' : '' ?>>+<?= $number ?> (<?= $code ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="tel" class="form-control" id="admin_phone" name="admin_phone" placeholder="612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_street" name="admin_street" placeholder="Direccion">
                                            <label>Direccion *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_number" name="admin_number" placeholder="Numero">
                                            <label>Numero *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_city" name="admin_city" placeholder="Ciudad">
                                            <label>Ciudad *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_state" name="admin_state" placeholder="Provincia">
                                            <label>Provincia <span class="es-required">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="admin_zipcode" name="admin_zipcode" placeholder="CP">
                                            <label>Codigo Postal *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="admin_country" name="admin_country">
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

                        <!-- Tech Contact -->
                        <div class="contact-section">
                            <div class="contact-section-header mb-3">
                                <h5><i class="bi bi-tools me-2"></i>Contacto Tecnico</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyFromOwner('tech')">
                                    <i class="bi bi-files me-1"></i>Copiar del Propietario
                                </button>
                            </div>

                            <?php if (!empty($contacts)): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccionar contacto existente:</label>
                                <select class="form-select" id="tech_existing" name="tech_existing" onchange="toggleContactForm('tech', this.value)">
                                    <option value="">-- Crear nuevo contacto --</option>
                                    <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"
                                        data-firstname="<?= htmlspecialchars($contact['first_name'] ?? '') ?>"
                                        data-lastname="<?= htmlspecialchars($contact['last_name'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($contact['email'] ?? '') ?>"
                                        data-phone="<?= htmlspecialchars($contact['phone'] ?? '') ?>"
                                        data-phonecode="<?= htmlspecialchars($contact['phone_code'] ?? '34') ?>"
                                        data-street="<?= htmlspecialchars($contact['address_street'] ?? '') ?>"
                                        data-number="<?= htmlspecialchars($contact['address_number'] ?? '') ?>"
                                        data-city="<?= htmlspecialchars($contact['address_city'] ?? '') ?>"
                                        data-state="<?= htmlspecialchars($contact['address_state'] ?? '') ?>"
                                        data-zipcode="<?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>"
                                        data-country="<?= htmlspecialchars($contact['address_country'] ?? 'ES') ?>">
                                        <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        (<?= htmlspecialchars($contact['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="contact-fields" id="techFields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_first_name" name="tech_first_name" placeholder="Nombre">
                                            <label>Nombre *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_last_name" name="tech_last_name" placeholder="Apellidos">
                                            <label>Apellidos *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="tech_email" name="tech_email" placeholder="Email">
                                            <label>Email *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Telefono *</label>
                                        <div class="input-group">
                                            <select class="form-select" id="tech_phone_code" name="tech_phone_code" style="max-width: 110px;">
                                                <?php foreach ($phoneCodes as $code => $number): ?>
                                                <option value="<?= $number ?>" <?= $code === 'ES' ? 'selected' : '' ?>>+<?= $number ?> (<?= $code ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="tel" class="form-control" id="tech_phone" name="tech_phone" placeholder="612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_street" name="tech_street" placeholder="Direccion">
                                            <label>Direccion *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_number" name="tech_number" placeholder="Numero">
                                            <label>Numero *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_city" name="tech_city" placeholder="Ciudad">
                                            <label>Ciudad *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_state" name="tech_state" placeholder="Provincia">
                                            <label>Provincia <span class="es-required">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="tech_zipcode" name="tech_zipcode" placeholder="CP">
                                            <label>Codigo Postal *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="tech_country" name="tech_country">
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

                        <!-- Billing Contact -->
                        <div class="contact-section">
                            <div class="contact-section-header mb-3">
                                <h5><i class="bi bi-credit-card me-2"></i>Contacto de Facturacion</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyFromOwner('billing')">
                                    <i class="bi bi-files me-1"></i>Copiar del Propietario
                                </button>
                            </div>

                            <?php if (!empty($contacts)): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccionar contacto existente:</label>
                                <select class="form-select" id="billing_existing" name="billing_existing" onchange="toggleContactForm('billing', this.value)">
                                    <option value="">-- Crear nuevo contacto --</option>
                                    <?php foreach ($contacts as $contact): ?>
                                    <option value="<?= $contact['id'] ?>"
                                        data-firstname="<?= htmlspecialchars($contact['first_name'] ?? '') ?>"
                                        data-lastname="<?= htmlspecialchars($contact['last_name'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($contact['email'] ?? '') ?>"
                                        data-phone="<?= htmlspecialchars($contact['phone'] ?? '') ?>"
                                        data-phonecode="<?= htmlspecialchars($contact['phone_code'] ?? '34') ?>"
                                        data-street="<?= htmlspecialchars($contact['address_street'] ?? '') ?>"
                                        data-number="<?= htmlspecialchars($contact['address_number'] ?? '') ?>"
                                        data-city="<?= htmlspecialchars($contact['address_city'] ?? '') ?>"
                                        data-state="<?= htmlspecialchars($contact['address_state'] ?? '') ?>"
                                        data-zipcode="<?= htmlspecialchars($contact['address_zipcode'] ?? '') ?>"
                                        data-country="<?= htmlspecialchars($contact['address_country'] ?? 'ES') ?>">
                                        <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        (<?= htmlspecialchars($contact['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="contact-fields" id="billingFields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_first_name" name="billing_first_name" placeholder="Nombre">
                                            <label>Nombre *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_last_name" name="billing_last_name" placeholder="Apellidos">
                                            <label>Apellidos *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="billing_email" name="billing_email" placeholder="Email">
                                            <label>Email *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Telefono *</label>
                                        <div class="input-group">
                                            <select class="form-select" id="billing_phone_code" name="billing_phone_code" style="max-width: 110px;">
                                                <?php foreach ($phoneCodes as $code => $number): ?>
                                                <option value="<?= $number ?>" <?= $code === 'ES' ? 'selected' : '' ?>>+<?= $number ?> (<?= $code ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="tel" class="form-control" id="billing_phone" name="billing_phone" placeholder="612345678">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_street" name="billing_street" placeholder="Direccion">
                                            <label>Direccion *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_number" name="billing_number" placeholder="Numero">
                                            <label>Numero *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_city" name="billing_city" placeholder="Ciudad">
                                            <label>Ciudad *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_state" name="billing_state" placeholder="Provincia">
                                            <label>Provincia <span class="es-required">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="billing_zipcode" name="billing_zipcode" placeholder="CP">
                                            <label>Codigo Postal *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="billing_country" name="billing_country">
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
                    </div>

                    <!-- SECCION: Opciones de Hosting/DNS -->
                    <?php $savedHostingType = $hosting_type ?? 'musedock_hosting'; ?>
                    <div class="options-section">
                        <h5><i class="bi bi-hdd-network me-2"></i>Tipo de Servicio</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="option-card<?= $savedHostingType === 'musedock_hosting' ? ' selected' : '' ?>" onclick="selectOption('hosting_type', 'musedock_hosting', this)">
                                    <input type="radio" name="hosting_type" value="musedock_hosting"<?= $savedHostingType === 'musedock_hosting' ? ' checked' : '' ?>>
                                    <div class="option-title"><i class="bi bi-cloud-check me-2"></i>DNS + Hosting MuseDock</div>
                                    <div class="option-desc">
                                        Tu dominio apuntara a tu sitio en MuseDock con SSL incluido.
                                        <br><small class="text-success">Recomendado</small>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="option-card<?= $savedHostingType === 'dns_only' ? ' selected' : '' ?>" onclick="selectOption('hosting_type', 'dns_only', this)">
                                    <input type="radio" name="hosting_type" value="dns_only"<?= $savedHostingType === 'dns_only' ? ' checked' : '' ?>>
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
                    <?php
                        $savedNsType = $ns_type ?? 'cloudflare';
                        $savedCustomNs = $custom_ns ?? [];
                    ?>
                    <div class="options-section">
                        <h5><i class="bi bi-server me-2"></i>Nameservers</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="option-card<?= $savedNsType === 'cloudflare' ? ' selected' : '' ?>" onclick="selectOption('ns_type', 'cloudflare', this); toggleCustomNS(false)">
                                    <input type="radio" name="ns_type" value="cloudflare"<?= $savedNsType === 'cloudflare' ? ' checked' : '' ?>>
                                    <div class="option-title"><i class="bi bi-shield-check me-2"></i>Cloudflare (Recomendado)</div>
                                    <div class="option-desc">
                                        Usaras los nameservers de Cloudflare para proteccion y velocidad.
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="option-card<?= $savedNsType === 'custom' ? ' selected' : '' ?>" onclick="selectOption('ns_type', 'custom', this); toggleCustomNS(true)">
                                    <input type="radio" name="ns_type" value="custom"<?= $savedNsType === 'custom' ? ' checked' : '' ?>>
                                    <div class="option-title"><i class="bi bi-pencil-square me-2"></i>Personalizados</div>
                                    <div class="option-desc">
                                        Usaras tus propios nameservers.
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="customNsContainer" style="display: <?= $savedNsType === 'custom' ? 'block' : 'none' ?>;">
                            <label class="form-label">Ingresa tus nameservers:</label>
                            <?php if (!empty($savedCustomNs)): ?>
                                <?php foreach ($savedCustomNs as $index => $ns): ?>
                                <div class="ns-input-group">
                                    <input type="text" class="form-control" name="custom_ns[]" placeholder="ns<?= $index + 1 ?>.tudominio.com" value="<?= htmlspecialchars($ns) ?>">
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="ns-input-group">
                                <input type="text" class="form-control" name="custom_ns[]" placeholder="ns1.tudominio.com">
                            </div>
                            <div class="ns-input-group">
                                <input type="text" class="form-control" name="custom_ns[]" placeholder="ns2.tudominio.com">
                            </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addNsField()">
                                <i class="bi bi-plus"></i> Agregar otro NS
                            </button>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-submit btn-lg" id="submitBtn">
                            <span id="submitBtnText"><i class="bi bi-arrow-right-circle me-2"></i>Continuar al Checkout</span>
                            <span id="submitBtnSpinner" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Procesando...
                            </span>
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
// Variable para saber si estamos editando un contacto existente
let isEditingExistingContact = false;

function toggleOwnerForm(value) {
    const fields = document.getElementById('ownerFields');
    const btnEdit = document.getElementById('btnEditOwner');

    if (value) {
        // Se selecciono un contacto existente - ocultar formulario, mostrar boton editar
        fields.style.display = 'none';
        if (btnEdit) btnEdit.style.display = 'block';
        isEditingExistingContact = false;
    } else {
        // Crear nuevo contacto - mostrar formulario, ocultar boton editar
        fields.style.display = 'block';
        if (btnEdit) btnEdit.style.display = 'none';
        isEditingExistingContact = false;
        // Limpiar campos al seleccionar "nuevo contacto"
        clearOwnerFields();
    }
}

// Editar el contacto seleccionado - cargar datos en el formulario
function editSelectedContact() {
    const select = document.getElementById('owner_existing');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption || !selectedOption.value) {
        return;
    }

    // Cargar datos del contacto en los campos
    document.getElementById('owner_first_name').value = selectedOption.dataset.firstname || '';
    document.getElementById('owner_last_name').value = selectedOption.dataset.lastname || '';
    document.getElementById('owner_company').value = selectedOption.dataset.company || '';
    document.getElementById('owner_company_reg_number').value = selectedOption.dataset.companyregnumber || '';
    document.getElementById('owner_email').value = selectedOption.dataset.email || '';
    document.getElementById('owner_phone').value = selectedOption.dataset.phone || '';
    document.getElementById('owner_street').value = selectedOption.dataset.street || '';
    document.getElementById('owner_number').value = selectedOption.dataset.number || '';
    document.getElementById('owner_city').value = selectedOption.dataset.city || '';
    document.getElementById('owner_state').value = selectedOption.dataset.state || '';
    document.getElementById('owner_zipcode').value = selectedOption.dataset.zipcode || '';

    // Seleccionar codigo de telefono
    const phoneCode = selectedOption.dataset.phonecode || '34';
    const phoneCodeSelect = document.getElementById('owner_phone_code');
    for (let i = 0; i < phoneCodeSelect.options.length; i++) {
        if (phoneCodeSelect.options[i].value === phoneCode) {
            phoneCodeSelect.selectedIndex = i;
            break;
        }
    }

    // Seleccionar pais
    const country = selectedOption.dataset.country || 'ES';
    const countrySelect = document.getElementById('owner_country');
    for (let i = 0; i < countrySelect.options.length; i++) {
        if (countrySelect.options[i].value === country) {
            countrySelect.selectedIndex = i;
            break;
        }
    }

    // Mostrar el formulario
    const fields = document.getElementById('ownerFields');
    fields.style.display = 'block';
    isEditingExistingContact = true;

    // Actualizar requisitos de provincia y CIF
    toggleStateRequired();
    toggleCompanyRegNumber();

    // Notificar al usuario
    Swal.fire({
        icon: 'info',
        title: 'Editando contacto',
        text: 'Modifica los datos que necesites. Los cambios se guardaran al continuar.',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
}

// Limpiar campos del owner
function clearOwnerFields() {
    const fields = ['first_name', 'last_name', 'company', 'company_reg_number', 'email', 'phone', 'street', 'number', 'city', 'state', 'zipcode'];
    fields.forEach(field => {
        const el = document.getElementById('owner_' + field);
        if (el) el.value = '';
    });
    // Ocultar campo CIF
    toggleCompanyRegNumber();
}

// Toggle form visibility for admin/tech/billing contacts based on selection
function toggleContactForm(contactType, value) {
    const fieldsId = contactType + 'Fields';
    const fields = document.getElementById(fieldsId);

    if (!fields) return;

    if (value) {
        // Se selecciono un contacto existente - ocultar formulario
        fields.style.display = 'none';
    } else {
        // Crear nuevo contacto - mostrar formulario y limpiar campos
        fields.style.display = 'block';
        clearContactFields(contactType);
    }
}

// Limpiar campos de un tipo de contacto
function clearContactFields(contactType) {
    const fields = ['first_name', 'last_name', 'email', 'phone', 'street', 'number', 'city', 'state', 'zipcode'];
    fields.forEach(field => {
        const el = document.getElementById(contactType + '_' + field);
        if (el) el.value = '';
    });
    // Resetear pais a ES
    const countryEl = document.getElementById(contactType + '_country');
    if (countryEl) {
        for (let i = 0; i < countryEl.options.length; i++) {
            if (countryEl.options[i].value === 'ES') {
                countryEl.selectedIndex = i;
                break;
            }
        }
    }
}

function toggleOtherContacts() {
    const checkbox = document.getElementById('same_contact_all');
    const container = document.getElementById('otherContactsContainer');
    container.style.display = checkbox.checked ? 'none' : 'block';
}

// Paises que requieren estado/provincia obligatorio
const countriesRequiringState = ['ES', 'US', 'MX', 'CA', 'AU', 'BR', 'AR', 'IN', 'DE'];

// Mostrar/ocultar requisito de provincia segun pais seleccionado
function toggleStateRequired() {
    const ownerCountry = document.getElementById('owner_country')?.value || 'ES';
    const requiresState = countriesRequiringState.includes(ownerCountry);

    // Mostrar hints y marcar como requerido segun pais
    document.querySelectorAll('.es-hint').forEach(el => {
        el.style.display = requiresState ? 'block' : 'none';
    });
    document.querySelectorAll('.es-required').forEach(el => {
        el.style.display = requiresState ? 'inline' : 'none';
    });
}

// Mostrar/ocultar campo CIF cuando hay empresa y dominio .ES
function toggleCompanyRegNumber() {
    const company = document.getElementById('owner_company')?.value?.trim() || '';
    const domain = '<?= htmlspecialchars($selectedDomain ?? '') ?>';
    const isEsDomain = domain.toLowerCase().endsWith('.es');
    const container = document.getElementById('owner_company_reg_container');

    if (container) {
        // Mostrar si hay empresa Y es dominio .ES
        if (company && isEsDomain) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
}

// Ejecutar al cargar
document.addEventListener('DOMContentLoaded', function() {
    toggleStateRequired();
    toggleCompanyRegNumber();
});

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

// Copiar datos del propietario a otro contacto
function copyFromOwner(targetType) {
    const ownerExisting = document.getElementById('owner_existing');
    const isUsingExistingContact = ownerExisting && ownerExisting.value && !isEditingExistingContact;

    // Si esta usando un contacto existente sin editar, obtener datos del select
    if (isUsingExistingContact) {
        const selectedOption = ownerExisting.options[ownerExisting.selectedIndex];

        // Mapeo de campos del data attribute al formulario
        const dataMapping = {
            'first_name': 'firstname',
            'last_name': 'lastname',
            'email': 'email',
            'phone': 'phone',
            'phone_code': 'phonecode',
            'street': 'street',
            'number': 'number',
            'city': 'city',
            'state': 'state',
            'zipcode': 'zipcode',
            'country': 'country'
        };

        Object.keys(dataMapping).forEach(field => {
            const targetEl = document.getElementById(targetType + '_' + field);
            const dataAttr = dataMapping[field];
            if (targetEl && selectedOption.dataset[dataAttr]) {
                targetEl.value = selectedOption.dataset[dataAttr];
            }
        });
    } else {
        // Copiar desde los campos del formulario del owner
        const fields = ['first_name', 'last_name', 'email', 'phone', 'phone_code', 'street', 'number', 'city', 'state', 'zipcode', 'country'];

        fields.forEach(field => {
            const sourceEl = document.getElementById('owner_' + field);
            const targetEl = document.getElementById(targetType + '_' + field);

            if (sourceEl && targetEl) {
                targetEl.value = sourceEl.value;
            }
        });
    }

    // Mostrar confirmacion
    Swal.fire({
        icon: 'success',
        title: 'Datos copiados',
        text: 'Se han copiado los datos del propietario',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000
    });
}

function submitRegistration(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // Definir campos requeridos con sus etiquetas en espanol
    const fieldLabels = {
        'owner_first_name': 'Nombre',
        'owner_last_name': 'Apellidos',
        'owner_email': 'Email',
        'owner_phone': 'Telefono',
        'owner_street': 'Direccion',
        'owner_number': 'Numero de direccion',
        'owner_city': 'Ciudad',
        'owner_zipcode': 'Codigo Postal',
        'owner_country': 'Pais'
    };

    // Validar contacto owner si es nuevo
    const ownerExisting = document.getElementById('owner_existing');
    if (!ownerExisting || !ownerExisting.value) {
        const required = ['owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone', 'owner_street', 'owner_number', 'owner_city', 'owner_zipcode', 'owner_country'];
        const missingFields = [];

        for (const field of required) {
            const value = formData.get(field)?.trim();
            if (!value) {
                missingFields.push(fieldLabels[field] || field);
            }
        }

        // Validar que el telefono no este vacio (no usar placeholder)
        const phoneValue = document.getElementById('owner_phone').value.trim();
        if (!phoneValue || phoneValue === '612345678') {
            if (!missingFields.includes('Telefono')) {
                missingFields.push('Telefono (ingresa un numero real)');
            }
        }

        // Validar formato email
        const emailValue = formData.get('owner_email')?.trim();
        if (emailValue && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
            Swal.fire({
                icon: 'error',
                title: 'Email invalido',
                text: 'Por favor ingresa un email valido',
                confirmButtonText: 'Entendido'
            });
            document.getElementById('owner_email')?.focus();
            return;
        }

        // Validar provincia/estado obligatorio segun pais
        const ownerCountry = formData.get('owner_country');
        if (countriesRequiringState.includes(ownerCountry)) {
            const stateValue = formData.get('owner_state')?.trim();
            if (!stateValue) {
                const stateLabel = ['US', 'MX', 'AU', 'BR', 'AR', 'IN'].includes(ownerCountry) ? 'Estado' : 'Provincia';
                missingFields.push(stateLabel + ' (obligatorio para ' + ownerCountry + ')');
            }
        }

        if (missingFields.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                html: '<p>Por favor completa los siguientes campos del contacto propietario:</p><ul style="text-align:left;margin-top:10px;">' +
                      missingFields.map(f => '<li>' + f + '</li>').join('') + '</ul>',
                confirmButtonText: 'Entendido'
            });
            // Enfocar el primer campo vacio
            const firstMissing = required.find(f => !formData.get(f)?.trim());
            if (firstMissing) {
                document.getElementById(firstMissing)?.focus();
            }
            return;
        }
    }

    // Validar otros contactos si no se usa el mismo
    const sameContactAll = document.getElementById('same_contact_all');
    if (sameContactAll && !sameContactAll.checked) {
        const contactTypes = ['admin', 'tech', 'billing'];
        const contactLabels = { 'admin': 'Administrativo', 'tech': 'Tecnico', 'billing': 'Facturacion' };

        for (const type of contactTypes) {
            const requiredOther = [type + '_first_name', type + '_last_name', type + '_email', type + '_phone', type + '_street', type + '_number', type + '_city', type + '_zipcode', type + '_country'];
            const missingOther = [];

            for (const field of requiredOther) {
                const value = formData.get(field)?.trim();
                if (!value) {
                    const fieldName = field.replace(type + '_', '');
                    missingOther.push(fieldLabels['owner_' + fieldName] || fieldName);
                }
            }

            // Validar telefono no vacio
            const phoneEl = document.getElementById(type + '_phone');
            if (phoneEl) {
                const phoneVal = phoneEl.value.trim();
                if (!phoneVal || phoneVal === '612345678') {
                    if (!missingOther.includes('Telefono')) {
                        missingOther.push('Telefono (ingresa un numero real)');
                    }
                }
            }

            if (missingOther.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos - Contacto ' + contactLabels[type],
                    html: '<p>Por favor completa los siguientes campos:</p><ul style="text-align:left;margin-top:10px;">' +
                          missingOther.map(f => '<li>' + f + '</li>').join('') + '</ul>',
                    confirmButtonText: 'Entendido'
                });
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
                text: 'Debes ingresar al menos 2 nameservers personalizados',
                confirmButtonText: 'Entendido'
            });
            return;
        }
    }

    // Mostrar spinner en el boton
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitBtnSpinner = document.getElementById('submitBtnSpinner');

    submitBtn.disabled = true;
    submitBtnText.style.display = 'none';
    submitBtnSpinner.style.display = 'inline';

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
            // Restaurar boton
            submitBtn.disabled = false;
            submitBtnText.style.display = 'inline';
            submitBtnSpinner.style.display = 'none';

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'Error al guardar datos',
                confirmButtonText: 'Entendido'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);

        // Restaurar boton
        submitBtn.disabled = false;
        submitBtnText.style.display = 'inline';
        submitBtnSpinner.style.display = 'none';

        Swal.fire({
            icon: 'error',
            title: 'Error de conexion',
            text: 'No se pudo conectar con el servidor. Intenta de nuevo.',
            confirmButtonText: 'Entendido'
        });
    });
}
</script>
@endsection
