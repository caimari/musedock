@extends('Customer.layout')

@section('styles')
<style>
    .dns-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .dns-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .record-type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        font-family: monospace;
        min-width: 60px;
        text-align: center;
    }
    .record-type-A { background: #e3f2fd; color: #1565c0; }
    .record-type-AAAA { background: #e8f5e9; color: #2e7d32; }
    .record-type-CNAME { background: #fff3e0; color: #ef6c00; }
    .record-type-MX { background: #fce4ec; color: #c2185b; }
    .record-type-TXT { background: #f3e5f5; color: #7b1fa2; }
    .record-type-NS { background: #e0f2f1; color: #00695c; }
    .record-type-SRV { background: #eceff1; color: #455a64; }
    .record-type-CAA { background: #fff8e1; color: #ff6f00; }
    .proxied-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
    }
    .proxied-on { background: #ff9800; color: white; }
    .proxied-off { background: #9e9e9e; color: white; }
    .record-row {
        transition: background-color 0.2s;
    }
    .record-row:hover {
        background-color: #f8f9fa;
    }
    .record-name {
        font-family: monospace;
        font-weight: 500;
    }
    .record-content {
        font-family: monospace;
        font-size: 0.85rem;
        word-break: break-all;
        max-width: 300px;
    }
    .ns-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .ns-item {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 8px;
        font-family: monospace;
    }
    .btn-action {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
</style>
@endsection

@section('content')
<?php
    $domain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');
    $zoneId = $order['cloudflare_zone_id'] ?? '';
    $nsServers = $zoneInfo['name_servers'] ?? [];
    $useCloudflareNs = $order['use_cloudflare_ns'] ?? 1;
    $customNs = json_decode($order['custom_nameservers'] ?? '[]', true) ?: [];
?>
<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-hdd-network me-2"></i>Gestor DNS</h4>
                <p class="text-muted mb-0">Administra los registros DNS de <strong><?= htmlspecialchars($domain) ?></strong></p>
            </div>
            <a href="/customer/dashboard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <?php if (empty($zoneId)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Este dominio no tiene una zona DNS configurada en Cloudflare.
        </div>
        <?php else: ?>

        <div class="row">
            <!-- Nameservers Section -->
            <div class="col-lg-4 mb-4">
                <div class="card dns-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-server me-2"></i>Nameservers</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($useCloudflareNs && !empty($nsServers)): ?>
                        <div class="alert alert-success mb-3">
                            <small><i class="bi bi-cloud-check me-1"></i>Usando Cloudflare NS</small>
                        </div>
                        <p class="small text-muted mb-2">Nameservers actuales:</p>
                        <?php foreach ($nsServers as $ns): ?>
                        <div class="ns-item">
                            <i class="bi bi-check-circle text-success me-2"></i><?= htmlspecialchars($ns) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php elseif (!empty($customNs)): ?>
                        <div class="alert alert-info mb-3">
                            <small><i class="bi bi-gear me-1"></i>Usando NS Personalizados</small>
                        </div>
                        <?php foreach ($customNs as $ns): ?>
                        <div class="ns-item"><?= htmlspecialchars($ns) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="restoreCloudflareNs()">
                            <i class="bi bi-cloud me-1"></i>Restaurar Cloudflare NS
                        </button>
                        <?php endif; ?>

                        <hr>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#nsModal">
                            <i class="bi bi-pencil me-1"></i>Cambiar Nameservers
                        </button>
                    </div>
                </div>
            </div>

            <!-- DNS Records Section -->
            <div class="col-lg-8 mb-4">
                <div class="card dns-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Registros DNS</h5>
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                            <i class="bi bi-plus-lg me-1"></i>Nuevo Registro
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:80px;">Tipo</th>
                                        <th>Nombre</th>
                                        <th>Contenido</th>
                                        <th style="width:70px;">TTL</th>
                                        <th style="width:70px;">Proxy</th>
                                        <th style="width:100px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="recordsTable">
                                    <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox me-2"></i>No hay registros DNS
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($records as $record): ?>
                                    <tr class="record-row" data-record-id="<?= htmlspecialchars($record['id']) ?>">
                                        <td>
                                            <span class="record-type-badge record-type-<?= htmlspecialchars($record['type']) ?>">
                                                <?= htmlspecialchars($record['type']) ?>
                                            </span>
                                        </td>
                                        <td class="record-name"><?= htmlspecialchars($record['name']) ?></td>
                                        <td class="record-content" title="<?= htmlspecialchars($record['content']) ?>">
                                            <?= htmlspecialchars(strlen($record['content']) > 50 ? substr($record['content'], 0, 50) . '...' : $record['content']) ?>
                                        </td>
                                        <td><?= $record['ttl'] == 1 ? 'Auto' : $record['ttl'] ?></td>
                                        <td>
                                            <?php if (in_array($record['type'], ['A', 'AAAA', 'CNAME'])): ?>
                                            <span class="proxied-badge <?= $record['proxied'] ? 'proxied-on' : 'proxied-off' ?>">
                                                <?= $record['proxied'] ? 'ON' : 'OFF' ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-action" onclick="editRecord('<?= htmlspecialchars($record['id']) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-action" onclick="deleteRecord('<?= htmlspecialchars($record['id']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Añadir Registro -->
<div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Registro DNS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRecordForm">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">

                    <div class="mb-3">
                        <label class="form-label">Tipo de Registro</label>
                        <select class="form-select" name="type" id="recordType" required onchange="updateRecordForm()">
                            <?php foreach ($recordTypes as $type => $label): ?>
                            <option value="<?= $type ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="name" placeholder="@ o subdominio" required>
                            <span class="input-group-text">.<?= htmlspecialchars($domain) ?></span>
                        </div>
                        <small class="text-muted">Usa @ para el dominio raiz</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <input type="text" class="form-control" name="content" id="recordContent" placeholder="Valor del registro" required>
                        <small class="text-muted" id="contentHelp">Direccion IP para registros A</small>
                    </div>

                    <div class="mb-3" id="priorityField" style="display:none;">
                        <label class="form-label">Prioridad (MX)</label>
                        <input type="number" class="form-control" name="priority" value="10" min="0" max="65535">
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">TTL</label>
                            <select class="form-select" name="ttl">
                                <option value="1">Auto</option>
                                <option value="300">5 minutos</option>
                                <option value="3600">1 hora</option>
                                <option value="86400">1 dia</option>
                            </select>
                        </div>
                        <div class="col-6" id="proxiedField">
                            <label class="form-label">
                                Proxy Cloudflare
                                <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip"
                                   title="Activado (naranja): El tráfico pasa por Cloudflare, ocultando tu IP real, con protección DDoS, firewall, cache y SSL automático. Desactivado (gris): DNS directo a tu servidor sin protecciones adicionales."></i>
                            </label>
                            <select class="form-select" name="proxied">
                                <option value="1">Activado (🟠 naranja) - Recomendado</option>
                                <option value="0">Desactivado (⚪ gris) - Solo DNS</option>
                            </select>
                            <small class="text-muted">
                                <strong>Naranja:</strong> Protección DDoS + SSL + Cache + Oculta tu IP real<br>
                                <strong>Gris:</strong> Solo resolución DNS sin protecciones
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createRecord()">
                    <i class="bi bi-plus-lg me-1"></i>Crear Registro
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Registro -->
<div class="modal fade" id="editRecordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Registro DNS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRecordForm">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="record_id" id="editRecordId">
                    <input type="hidden" name="type" id="editRecordType">

                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" class="form-control" id="editRecordTypeDisplay" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="name" id="editRecordName" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenido</label>
                        <input type="text" class="form-control" name="content" id="editRecordContent" required>
                    </div>

                    <div class="mb-3" id="editPriorityField" style="display:none;">
                        <label class="form-label">Prioridad</label>
                        <input type="number" class="form-control" name="priority" id="editRecordPriority">
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">TTL</label>
                            <select class="form-select" name="ttl" id="editRecordTtl">
                                <option value="1">Auto</option>
                                <option value="300">5 minutos</option>
                                <option value="3600">1 hora</option>
                                <option value="86400">1 dia</option>
                            </select>
                        </div>
                        <div class="col-6" id="editProxiedField">
                            <label class="form-label">
                                Proxy Cloudflare
                                <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip"
                                   title="Activado (naranja): El tráfico pasa por Cloudflare, ocultando tu IP real, con protección DDoS, firewall, cache y SSL automático. Desactivado (gris): DNS directo a tu servidor sin protecciones adicionales."></i>
                            </label>
                            <select class="form-select" name="proxied" id="editRecordProxied">
                                <option value="1">Activado (🟠 naranja) - Recomendado</option>
                                <option value="0">Desactivado (⚪ gris) - Solo DNS</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="updateRecord()">
                    <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Nameservers -->
<div class="modal fade" id="nsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-server me-2"></i>Cambiar Nameservers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Importante:</strong> Cambiar los nameservers puede afectar la resolucion de tu dominio.
                    Los cambios pueden tardar hasta 48 horas en propagarse.
                </div>
                <form id="nsForm">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?>">

                    <div class="mb-3">
                        <label class="form-label">Nameserver 1 *</label>
                        <input type="text" class="form-control" name="nameservers[]" placeholder="ns1.ejemplo.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nameserver 2 *</label>
                        <input type="text" class="form-control" name="nameservers[]" placeholder="ns2.ejemplo.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nameserver 3 (opcional)</label>
                        <input type="text" class="form-control" name="nameservers[]" placeholder="ns3.ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nameserver 4 (opcional)</label>
                        <input type="text" class="form-control" name="nameservers[]" placeholder="ns4.ejemplo.com">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="updateNameservers()">
                    <i class="bi bi-arrow-repeat me-1"></i>Actualizar NS
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const orderId = <?= $order['id'] ?>;
const csrfToken = '<?= $csrf_token ?>';
const recordsData = <?= json_encode($records) ?>;

function updateRecordForm() {
    const type = document.getElementById('recordType').value;
    const priorityField = document.getElementById('priorityField');
    const proxiedField = document.getElementById('proxiedField');
    const contentHelp = document.getElementById('contentHelp');

    priorityField.style.display = type === 'MX' ? 'block' : 'none';
    proxiedField.style.display = ['A', 'AAAA', 'CNAME'].includes(type) ? 'block' : 'none';

    const helpTexts = {
        'A': 'Direccion IPv4 (ej: 192.168.1.1)',
        'AAAA': 'Direccion IPv6 (ej: 2001:db8::1)',
        'CNAME': 'Dominio destino (ej: ejemplo.com)',
        'MX': 'Servidor de correo (ej: mail.ejemplo.com)',
        'TXT': 'Texto (ej: v=spf1 include:...)',
        'NS': 'Servidor de nombres (ej: ns1.ejemplo.com)',
        'SRV': 'Formato: prioridad peso puerto destino',
        'CAA': 'Formato: 0 issue "ca.ejemplo.com"'
    };

    contentHelp.textContent = helpTexts[type] || '';
}

function createRecord() {
    const form = document.getElementById('addRecordForm');
    const formData = new FormData(form);

    Swal.fire({
        title: 'Creando registro...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/dns/records`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Registro creado',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'No se pudo crear el registro', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexion', 'error'));
}

function editRecord(recordId) {
    const record = recordsData.find(r => r.id === recordId);
    if (!record) return;

    document.getElementById('editRecordId').value = record.id;
    document.getElementById('editRecordType').value = record.type;
    document.getElementById('editRecordTypeDisplay').value = record.type;
    document.getElementById('editRecordName').value = record.name;
    document.getElementById('editRecordContent').value = record.content;
    document.getElementById('editRecordTtl').value = record.ttl;
    document.getElementById('editRecordProxied').value = record.proxied ? '1' : '0';

    if (record.type === 'MX') {
        document.getElementById('editPriorityField').style.display = 'block';
        document.getElementById('editRecordPriority').value = record.priority || 10;
    } else {
        document.getElementById('editPriorityField').style.display = 'none';
    }

    document.getElementById('editProxiedField').style.display =
        ['A', 'AAAA', 'CNAME'].includes(record.type) ? 'block' : 'none';

    new bootstrap.Modal(document.getElementById('editRecordModal')).show();
}

function updateRecord() {
    const form = document.getElementById('editRecordForm');
    const formData = new FormData(form);
    const recordId = document.getElementById('editRecordId').value;

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/dns/records/${recordId}/update`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Registro actualizado',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'No se pudo actualizar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexion', 'error'));
}

function deleteRecord(recordId) {
    Swal.fire({
        title: 'Eliminar registro?',
        text: 'Esta accion no se puede deshacer',
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

            fetch(`/customer/domain/${orderId}/dns/records/${recordId}/delete`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo eliminar', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexion', 'error'));
        }
    });
}

function updateNameservers() {
    const form = document.getElementById('nsForm');
    const formData = new FormData(form);

    Swal.fire({
        title: 'Actualizando nameservers...',
        html: 'Esto puede tardar unos segundos',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/dns/nameservers`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Nameservers actualizados',
                text: 'Los cambios pueden tardar hasta 48h en propagarse',
                confirmButtonText: 'Entendido'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.error || 'No se pudo actualizar', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de conexion', 'error'));
}

function restoreCloudflareNs() {
    Swal.fire({
        title: 'Restaurar Cloudflare NS?',
        text: 'Se restauraran los nameservers de Cloudflare',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Si, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            fetch(`/customer/domain/${orderId}/nameservers/restore-cloudflare`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo restaurar', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexion', 'error'));
        }
    });
}

// Initialize
updateRecordForm();
</script>
@endsection
