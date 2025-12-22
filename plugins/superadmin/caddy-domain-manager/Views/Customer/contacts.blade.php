@extends('Customer.layout')

@section('styles')
<style>
    .contacts-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .contacts-header h2 {
        margin: 0;
    }

    .contact-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s;
    }

    .contact-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .contact-card.is-default {
        border-left: 4px solid #667eea;
    }

    .contact-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .contact-info {
        flex: 1;
    }

    .contact-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .contact-company {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .contact-details {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 0.85rem;
        color: #555;
    }

    .contact-details span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .contact-details i {
        color: #667eea;
    }

    .contact-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .badge-default {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .badge-domains {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .badge-domains.warning {
        background: #fff3e0;
        color: #f57c00;
    }

    .contact-body {
        padding: 15px 20px;
        background: #fafafa;
    }

    .domains-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .domains-list li {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        background: white;
        border-radius: 15px;
        font-size: 0.8rem;
        margin-right: 8px;
        margin-bottom: 5px;
        border: 1px solid #e0e0e0;
    }

    .domains-list .role-badge {
        font-size: 0.65rem;
        padding: 1px 5px;
        border-radius: 8px;
        background: #f5f5f5;
        color: #666;
    }

    .role-badge.owner { background: #e8f5e9; color: #388e3c; }
    .role-badge.admin { background: #e3f2fd; color: #1976d2; }
    .role-badge.tech { background: #fff3e0; color: #f57c00; }
    .role-badge.billing { background: #fce4ec; color: #c2185b; }

    .contact-actions {
        padding: 15px 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        border-top: 1px solid #f0f0f0;
    }

    .contact-actions .btn {
        font-size: 0.85rem;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .empty-state i {
        font-size: 4rem;
        color: #ddd;
        margin-bottom: 20px;
    }

    .empty-state h4 {
        color: #666;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #999;
        margin-bottom: 20px;
    }

    /* Edit Modal */
    .edit-form .form-label {
        font-weight: 500;
        color: #555;
        font-size: 0.85rem;
    }

    .warning-box {
        background: #fff3e0;
        border: 1px solid #ffb74d;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .warning-box i {
        color: #f57c00;
    }

    .warning-box strong {
        color: #e65100;
    }
</style>
@endsection

@section('content')
<div class="contacts-header">
    <h2><i class="bi bi-person-lines-fill me-2"></i>Mis Contactos</h2>
</div>

<p class="text-muted mb-4">
    Gestiona tus contactos de registro de dominios. Los cambios que realices aqui afectaran a todos los dominios asociados a cada contacto.
</p>

<?php if (empty($contacts)): ?>
<div class="empty-state">
    <i class="bi bi-person-x"></i>
    <h4>No tienes contactos guardados</h4>
    <p>Los contactos se crean automaticamente cuando registras un dominio.</p>
    <a href="/customer/register-domain" class="btn btn-primary">
        <i class="bi bi-globe me-2"></i>Registrar un Dominio
    </a>
</div>
<?php else: ?>

<?php foreach ($contacts as $contact): ?>
<div class="contact-card <?= $contact['is_default'] ? 'is-default' : '' ?>">
    <div class="contact-header">
        <div class="contact-info">
            <div class="contact-name">
                <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
            </div>
            <?php if (!empty($contact['company'])): ?>
            <div class="contact-company">
                <i class="bi bi-building"></i> <?= htmlspecialchars($contact['company']) ?>
                <?php if (!empty($contact['company_reg_number'])): ?>
                    <small class="text-muted">(<?= htmlspecialchars($contact['company_reg_number']) ?>)</small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="contact-details">
                <span><i class="bi bi-envelope"></i> <?= htmlspecialchars($contact['email']) ?></span>
                <span><i class="bi bi-telephone"></i> <?= htmlspecialchars($contact['phone']) ?></span>
                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($contact['address_city'] . ', ' . $contact['address_country']) ?></span>
            </div>
        </div>
        <div class="contact-badges">
            <?php if ($contact['is_default']): ?>
            <span class="badge-default"><i class="bi bi-star-fill me-1"></i>Predeterminado</span>
            <?php endif; ?>
            <?php if ($contact['domains_count'] > 0): ?>
            <span class="badge-domains <?= $contact['domains_count'] > 1 ? 'warning' : '' ?>">
                <i class="bi bi-globe2 me-1"></i><?= $contact['domains_count'] ?> dominio<?= $contact['domains_count'] > 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($contact['associated_domains'])): ?>
    <div class="contact-body">
        <small class="text-muted d-block mb-2">Dominios asociados:</small>
        <ul class="domains-list">
            <?php foreach ($contact['associated_domains'] as $domain): ?>
            <li>
                <i class="bi bi-globe2"></i>
                <?= htmlspecialchars($domain['full_domain']) ?>
                <span class="role-badge <?= $domain['role'] ?>"><?= ucfirst($domain['role']) ?></span>
            </li>
            <?php endforeach; ?>
            <?php if ($contact['domains_count'] > 10): ?>
            <li class="text-muted">...y <?= $contact['domains_count'] - 10 ?> mas</li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="contact-actions">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="editContact(<?= $contact['id'] ?>)">
            <i class="bi bi-pencil me-1"></i>Editar
        </button>
        <?php if (!$contact['is_default']): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDefault(<?= $contact['id'] ?>)">
            <i class="bi bi-star me-1"></i>Establecer como predeterminado
        </button>
        <?php endif; ?>
        <?php if ($contact['domains_count'] == 0): ?>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteContact(<?= $contact['id'] ?>)">
            <i class="bi bi-trash me-1"></i>Eliminar
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Modal Editar Contacto -->
<div class="modal fade" id="editContactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Contacto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editContactForm" onsubmit="submitEditContact(event)">
                <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="contact_id" id="editContactId">

                <div class="modal-body">
                    <!-- Warning box para contactos con multiples dominios -->
                    <div class="warning-box" id="multiDomainWarning" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Atencion:</strong> Este contacto esta asociado a <span id="warningDomainsCount">0</span> dominios.
                        Los cambios que realices afectaran a todos ellos.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellidos *</label>
                            <input type="text" class="form-control" name="last_name" id="editLastName" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Empresa (opcional)</label>
                            <input type="text" class="form-control" name="company" id="editCompany">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CIF/NIF</label>
                            <input type="text" class="form-control" name="company_reg_number" id="editCompanyRegNumber">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefono *</label>
                            <div class="input-group">
                                <select class="form-select" name="phone_code" id="editPhoneCode" style="max-width: 100px;">
                                    <option value="34">+34</option>
                                    <option value="1">+1</option>
                                    <option value="52">+52</option>
                                    <option value="44">+44</option>
                                    <option value="49">+49</option>
                                    <option value="33">+33</option>
                                </select>
                                <input type="text" class="form-control" name="phone" id="editPhone" required>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Direccion *</label>
                            <input type="text" class="form-control" name="address_street" id="editStreet" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Numero *</label>
                            <input type="text" class="form-control" name="address_number" id="editNumber">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ciudad *</label>
                            <input type="text" class="form-control" name="address_city" id="editCity" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Provincia</label>
                            <input type="text" class="form-control" name="address_state" id="editState">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Codigo Postal *</label>
                            <input type="text" class="form-control" name="address_zipcode" id="editZipcode" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pais *</label>
                            <select class="form-select" name="address_country" id="editCountry" required>
                                <option value="ES">Espana</option>
                                <option value="US">Estados Unidos</option>
                                <option value="MX">Mexico</option>
                                <option value="AR">Argentina</option>
                                <option value="CO">Colombia</option>
                                <option value="CL">Chile</option>
                                <option value="PE">Peru</option>
                                <option value="GB">Reino Unido</option>
                                <option value="DE">Alemania</option>
                                <option value="FR">Francia</option>
                                <option value="IT">Italia</option>
                                <option value="PT">Portugal</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveContact">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = '<?= $csrf_token ?>';
let editModal = null;
let currentContactDomainsCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editContactModal'));
});

function editContact(contactId) {
    // Obtener datos del contacto
    fetch(`/customer/contacts/${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const contact = data.contact;

                document.getElementById('editContactId').value = contactId;
                document.getElementById('editFirstName').value = contact.first_name || '';
                document.getElementById('editLastName').value = contact.last_name || '';
                document.getElementById('editCompany').value = contact.company || '';
                document.getElementById('editCompanyRegNumber').value = contact.company_reg_number || '';
                document.getElementById('editEmail').value = contact.email || '';
                document.getElementById('editPhone').value = contact.phone || '';
                document.getElementById('editStreet').value = contact.address_street || '';
                document.getElementById('editNumber').value = contact.address_number || '';
                document.getElementById('editCity').value = contact.address_city || '';
                document.getElementById('editState').value = contact.address_state || '';
                document.getElementById('editZipcode').value = contact.address_zipcode || '';

                // Seleccionar pais
                const countrySelect = document.getElementById('editCountry');
                for (let i = 0; i < countrySelect.options.length; i++) {
                    if (countrySelect.options[i].value === contact.address_country) {
                        countrySelect.selectedIndex = i;
                        break;
                    }
                }

                // Buscar el domains_count de este contacto
                const contactCard = document.querySelector(`[onclick="editContact(${contactId})"]`).closest('.contact-card');
                const badgeDomains = contactCard.querySelector('.badge-domains');
                currentContactDomainsCount = badgeDomains ? parseInt(badgeDomains.textContent.match(/\d+/)[0]) : 0;

                // Mostrar warning si tiene multiples dominios
                const warningBox = document.getElementById('multiDomainWarning');
                if (currentContactDomainsCount > 1) {
                    document.getElementById('warningDomainsCount').textContent = currentContactDomainsCount;
                    warningBox.style.display = 'block';
                } else {
                    warningBox.style.display = 'none';
                }

                editModal.show();
            } else {
                Swal.fire('Error', data.error || 'No se pudo cargar el contacto', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Error de conexion', 'error');
        });
}

function submitEditContact(event) {
    event.preventDefault();

    const contactId = document.getElementById('editContactId').value;
    const form = document.getElementById('editContactForm');
    const formData = new FormData(form);

    // Confirmar si afecta multiples dominios
    if (currentContactDomainsCount > 1) {
        Swal.fire({
            title: 'Confirmar cambios',
            html: `<p>Este contacto esta asociado a <strong>${currentContactDomainsCount} dominios</strong>.</p>
                   <p>Los cambios afectaran a todos ellos. ¿Deseas continuar?</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Si, guardar cambios',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                saveContact(contactId, formData);
            }
        });
    } else {
        saveContact(contactId, formData);
    }
}

function saveContact(contactId, formData) {
    const btn = document.getElementById('btnSaveContact');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    fetch(`/customer/contacts/${contactId}/update`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar Cambios';

        if (data.success) {
            editModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'Contacto actualizado',
                text: data.message,
                confirmButtonColor: '#667eea'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo actualizar el contacto', 'error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar Cambios';
        Swal.fire('Error', 'Error de conexion', 'error');
    });
}

function setDefault(contactId) {
    Swal.fire({
        title: 'Establecer como predeterminado',
        text: 'Este contacto se usara por defecto al registrar nuevos dominios.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, establecer',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            fetch(`/customer/contacts/${contactId}/set-default`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Listo',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Error de conexion', 'error');
            });
        }
    });
}

function deleteContact(contactId) {
    Swal.fire({
        title: '¿Eliminar contacto?',
        text: 'Esta accion no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            fetch(`/customer/contacts/${contactId}/delete`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Error de conexion', 'error');
            });
        }
    });
}
</script>
@endsection
