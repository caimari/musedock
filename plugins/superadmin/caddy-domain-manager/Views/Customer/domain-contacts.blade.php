@extends('Customer.layout')

@section('styles')
<style>
    .contacts-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .contacts-card .card-header {
        background: linear-gradient(135deg, #6f42c1, #5a359a);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .domain-display {
        font-size: 1.5rem;
        font-weight: bold;
        margin-top: 8px;
    }
    .contact-type-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 4px solid #6f42c1;
        transition: all 0.3s ease;
    }
    .contact-type-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .contact-type-card.owner { border-left-color: #28a745; }
    .contact-type-card.admin { border-left-color: #17a2b8; }
    .contact-type-card.tech { border-left-color: #ffc107; }
    .contact-type-card.billing { border-left-color: #dc3545; }
    .contact-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .contact-type-badge.owner { background: #d4edda; color: #155724; }
    .contact-type-badge.admin { background: #d1ecf1; color: #0c5460; }
    .contact-type-badge.tech { background: #fff3cd; color: #856404; }
    .contact-type-badge.billing { background: #f8d7da; color: #721c24; }
    .contact-handle {
        font-family: monospace;
        background: #e9ecef;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    .contact-info {
        margin-top: 15px;
        line-height: 1.8;
    }
    .contact-info .label {
        color: #6c757d;
        font-size: 0.8rem;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .contact-info .value {
        font-weight: 500;
    }
    .btn-change-contact {
        background: transparent;
        border: 1px solid #6f42c1;
        color: #6f42c1;
        border-radius: 20px;
        padding: 5px 15px;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .btn-change-contact:hover {
        background: #6f42c1;
        color: white;
    }
    .sandbox-warning {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .info-box {
        background: #e3f8fc;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .contact-selector {
        max-height: 300px;
        overflow-y: auto;
    }
    .contact-option {
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .contact-option:hover {
        border-color: #6f42c1;
        background: #f8f5ff;
    }
    .contact-option.selected {
        border-color: #6f42c1;
        background: #f8f5ff;
        box-shadow: 0 0 0 2px rgba(111, 66, 193, 0.2);
    }
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 12px;
    }
    .back-link {
        color: #6c757d;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        margin-bottom: 20px;
    }
    .back-link:hover {
        color: #6f42c1;
    }
    .back-link i {
        margin-right: 5px;
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    <a href="/customer/dashboard" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al Dashboard
    </a>

    @php
        $fullDomain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');
        $orderStatus = $order['status'] ?? '';
        $isEditable = in_array($orderStatus, ['active', 'registered'], true) && !empty($order['openprovider_domain_id']);
    @endphp

    @if($openprovider_mode === 'sandbox')
    <div class="sandbox-warning">
        <i class="fas fa-flask"></i>
        <strong>Modo Sandbox</strong> - Los cambios se realizan en el entorno de pruebas de OpenProvider.
    </div>
    @endif

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card contacts-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-address-book"></i> Contactos del Dominio</h4>
                    <div class="domain-display">{{ $fullDomain }}</div>
                </div>
                <div class="card-body p-4">

                    @if(!$isEditable)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        El dominio no está listo para edición de contactos. Debe estar registrado/activo y tener ID en OpenProvider.
                    </div>
                    @else

                    <div class="info-box mb-4">
                        <i class="fas fa-info-circle"></i>
                        Los dominios tienen 4 tipos de contactos:
                        <strong>Owner</strong> (propietario legal),
                        <strong>Admin</strong> (contacto administrativo),
                        <strong>Tech</strong> (contacto tecnico) y
                        <strong>Billing</strong> (contacto de facturacion).
                    </div>

                    <!-- Owner Contact -->
                    @php $handleType = 'owner_handle'; $contact = $contactDetails[$handleType] ?? null; @endphp
                    <div class="contact-type-card owner" id="card-owner">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="contact-type-badge owner">Owner</span>
                                <span class="contact-handle ms-2">{{ $order['owner_handle'] ?? 'No asignado' }}</span>
                            </div>
                            <button type="button" class="btn-change-contact" onclick="openChangeModal('owner_handle', '{{ $order['owner_handle'] ?? '' }}')">
                                <i class="fas fa-edit"></i> Cambiar
                            </button>
                        </div>
                        @if($contact)
                        <div class="contact-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="label">Nombre</div>
                                    <div class="value">{{ $contact['name']['firstName'] ?? '' }} {{ $contact['name']['lastName'] ?? '' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Empresa</div>
                                    <div class="value">{{ $contact['companyName'] ?? '-' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Email</div>
                                    <div class="value">{{ $contact['email'] ?? '-' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Telefono</div>
                                    <div class="value">{{ $contact['phone']['countryCode'] ?? '' }}{{ $contact['phone']['subscriberNumber'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Admin Contact -->
                    @php $handleType = 'admin_handle'; $contact = $contactDetails[$handleType] ?? null; @endphp
                    <div class="contact-type-card admin" id="card-admin">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="contact-type-badge admin">Admin</span>
                                <span class="contact-handle ms-2">{{ $order['admin_handle'] ?? 'No asignado' }}</span>
                            </div>
                            <button type="button" class="btn-change-contact" onclick="openChangeModal('admin_handle', '{{ $order['admin_handle'] ?? '' }}')">
                                <i class="fas fa-edit"></i> Cambiar
                            </button>
                        </div>
                        @if($contact)
                        <div class="contact-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="label">Nombre</div>
                                    <div class="value">{{ $contact['name']['firstName'] ?? '' }} {{ $contact['name']['lastName'] ?? '' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Email</div>
                                    <div class="value">{{ $contact['email'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Tech Contact -->
                    @php $handleType = 'tech_handle'; $contact = $contactDetails[$handleType] ?? null; @endphp
                    <div class="contact-type-card tech" id="card-tech">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="contact-type-badge tech">Tech</span>
                                <span class="contact-handle ms-2">{{ $order['tech_handle'] ?? 'No asignado' }}</span>
                            </div>
                            <button type="button" class="btn-change-contact" onclick="openChangeModal('tech_handle', '{{ $order['tech_handle'] ?? '' }}')">
                                <i class="fas fa-edit"></i> Cambiar
                            </button>
                        </div>
                        @if($contact)
                        <div class="contact-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="label">Nombre</div>
                                    <div class="value">{{ $contact['name']['firstName'] ?? '' }} {{ $contact['name']['lastName'] ?? '' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Email</div>
                                    <div class="value">{{ $contact['email'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Billing Contact -->
                    @php $handleType = 'billing_handle'; $contact = $contactDetails[$handleType] ?? null; @endphp
                    <div class="contact-type-card billing" id="card-billing">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="contact-type-badge billing">Billing</span>
                                <span class="contact-handle ms-2">{{ $order['billing_handle'] ?? 'No asignado' }}</span>
                            </div>
                            <button type="button" class="btn-change-contact" onclick="openChangeModal('billing_handle', '{{ $order['billing_handle'] ?? '' }}')">
                                <i class="fas fa-edit"></i> Cambiar
                            </button>
                        </div>
                        @if($contact)
                        <div class="contact-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="label">Nombre</div>
                                    <div class="value">{{ $contact['name']['firstName'] ?? '' }} {{ $contact['name']['lastName'] ?? '' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="label">Email</div>
                                    <div class="value">{{ $contact['email'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambiar Contacto -->
<div class="modal fade" id="changeContactModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Cambiar Contacto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-handle-type" value="">
                <input type="hidden" id="modal-current-handle" value="">

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-existing">Contacto Existente</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-new">Nuevo Contacto</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab: Seleccionar Existente -->
                    <div class="tab-pane fade show active" id="tab-existing">
                        <div class="contact-selector">
                            @forelse($localContacts as $localContact)
                            <div class="contact-option" data-handle="{{ $localContact['openprovider_handle'] ?? '' }}" onclick="selectContact(this)">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $localContact['first_name'] }} {{ $localContact['last_name'] }}</strong>
                                    @if($localContact['openprovider_handle'])
                                    <span class="contact-handle">{{ $localContact['openprovider_handle'] }}</span>
                                    @endif
                                </div>
                                <div class="text-muted small">
                                    {{ $localContact['email'] }} | {{ $localContact['country'] }}
                                    @if($localContact['company_name'])
                                    | {{ $localContact['company_name'] }}
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p>No tienes contactos guardados. Crea uno nuevo.</p>
                            </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Tab: Nuevo Contacto -->
                    <div class="tab-pane fade" id="tab-new">
                        <form id="new-contact-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" name="new_first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" name="new_last_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Empresa</label>
                                    <input type="text" class="form-control" name="new_company_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="new_email" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cod. Pais</label>
                                    <input type="text" class="form-control" name="new_phone_country" value="+34">
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Telefono *</label>
                                    <input type="text" class="form-control" name="new_phone" required>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Direccion *</label>
                                    <input type="text" class="form-control" name="new_address" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Numero</label>
                                    <input type="text" class="form-control" name="new_address_number">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ciudad *</label>
                                    <input type="text" class="form-control" name="new_city" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cod. Postal</label>
                                    <input type="text" class="form-control" name="new_zipcode">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Pais *</label>
                                    <select class="form-select" name="new_country" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="ES">Espana</option>
                                        <option value="US">Estados Unidos</option>
                                        <option value="MX">Mexico</option>
                                        <option value="AR">Argentina</option>
                                        <option value="CO">Colombia</option>
                                        <option value="CL">Chile</option>
                                        <option value="PE">Peru</option>
                                        <option value="DE">Alemania</option>
                                        <option value="FR">Francia</option>
                                        <option value="GB">Reino Unido</option>
                                        <option value="IT">Italia</option>
                                        <option value="PT">Portugal</option>
                                        <option value="BR">Brasil</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NIF/VAT</label>
                                    <input type="text" class="form-control" name="new_vat">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveContactChange()" id="btn-save-contact">
                    <i class="fas fa-save"></i> Guardar Cambio
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = '{{ $csrf_token }}';
const orderId = {{ $order['id'] }};
let selectedHandle = null;

function openChangeModal(handleType, currentHandle) {
    document.getElementById('modal-handle-type').value = handleType;
    document.getElementById('modal-current-handle').value = currentHandle;
    selectedHandle = null;

    // Limpiar selecciones
    document.querySelectorAll('.contact-option').forEach(opt => opt.classList.remove('selected'));

    // Seleccionar el actual si existe
    const currentOption = document.querySelector(`.contact-option[data-handle="${currentHandle}"]`);
    if (currentOption) {
        currentOption.classList.add('selected');
        selectedHandle = currentHandle;
    }

    // Limpiar formulario
    document.getElementById('new-contact-form').reset();

    const modal = new bootstrap.Modal(document.getElementById('changeContactModal'));
    modal.show();
}

function selectContact(element) {
    document.querySelectorAll('.contact-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    selectedHandle = element.dataset.handle;
}

async function saveContactChange() {
    const handleType = document.getElementById('modal-handle-type').value;
    const btn = document.getElementById('btn-save-contact');
    const activeTab = document.querySelector('.tab-pane.active').id;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        if (activeTab === 'tab-existing') {
            // Usar contacto existente
            if (!selectedHandle) {
                alert('Selecciona un contacto');
                return;
            }
            formData.append(handleType, selectedHandle);
        } else {
            // Crear nuevo contacto
            formData.append('create_new_contact', 'true');
            formData.append('new_contact_type', handleType);

            const form = document.getElementById('new-contact-form');
            const formDataNew = new FormData(form);
            for (const [key, value] of formDataNew.entries()) {
                formData.append(key, value);
            }
        }

        const response = await fetch(`/customer/domain/${orderId}/contacts/update`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('Contacto actualizado correctamente');
            location.reload();
        } else {
            alert(data.message || 'Error al actualizar');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambio';
    }
}
</script>
@endsection
