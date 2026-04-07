@extends('Customer.layout')

@section('panel_content')
<style>
.ct-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.ct-header h2 { margin:0; font-size:1.1rem; font-weight:700; color:#243141; }
.ct-header h2 i { margin-right:6px; color:#4e73df; }
.ct-desc { font-size:0.82rem; color:#8a94a6; margin-bottom:18px; }

.ct-empty { text-align:center; padding:50px 20px; background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.ct-empty i { font-size:3rem; color:#ccc; display:block; margin-bottom:14px; }
.ct-empty h4 { font-size:0.95rem; color:#243141; margin:0 0 6px; }
.ct-empty p { font-size:0.82rem; color:#8a94a6; margin:0 0 16px; }
.ct-empty-btn { display:inline-flex; align-items:center; padding:8px 18px; font-size:0.82rem; font-weight:600; color:#fff; background:#4e73df; border:none; border-radius:8px; cursor:pointer; text-decoration:none; }
.ct-empty-btn i { margin-right:6px; }
.ct-empty-btn:hover { background:#3b5ec2; color:#fff; }

.ct-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:14px; overflow:hidden; transition:box-shadow 0.2s; }
.ct-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.09); }
.ct-card.ct-default { border-left:4px solid #4e73df; }

.ct-card-top { display:flex; justify-content:space-between; align-items:flex-start; padding:14px 16px; gap:10px; }
.ct-info { flex:1; min-width:0; }
.ct-name { font-size:0.95rem; font-weight:600; color:#243141; margin-bottom:3px; }
.ct-company { font-size:0.82rem; color:#8a94a6; margin-bottom:6px; }
.ct-company i { margin-right:6px; color:#4e73df; }
.ct-details { display:flex; flex-wrap:wrap; gap:12px; font-size:0.82rem; color:#5a6a7e; }
.ct-details span { display:inline-flex; align-items:center; }
.ct-details i { margin-right:6px; color:#4e73df; font-size:0.82rem; }

.ct-badges { display:flex; flex-wrap:wrap; gap:6px; flex-shrink:0; }
.ct-badge-default { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:600; background:linear-gradient(135deg,#4e73df,#3d5fc4); color:#fff; }
.ct-badge-default i { margin-right:6px; }
.ct-badge-domains { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:500; background:#e8f0fe; color:#4e73df; }
.ct-badge-domains i { margin-right:6px; }

.ct-actions { display:flex; flex-wrap:wrap; gap:8px; padding:10px 16px; border-top:1px solid #f0f2f5; }
.ct-btn { display:inline-flex; align-items:center; padding:5px 14px; font-size:0.82rem; font-weight:500; border-radius:8px; border:1px solid transparent; cursor:pointer; transition:all 0.15s; background:none; text-decoration:none; }
.ct-btn i { margin-right:6px; }
.ct-btn-edit { color:#4e73df; border-color:#4e73df; }
.ct-btn-edit:hover { background:#4e73df; color:#fff; }
.ct-btn-default { color:#28a745; border-color:#28a745; }
.ct-btn-default:hover { background:#28a745; color:#fff; }
.ct-btn-delete { color:#dc3545; border-color:#dc3545; }
.ct-btn-delete:hover { background:#dc3545; color:#fff; }

@media(max-width:600px) {
  .ct-card-top { flex-direction:column; }
  .ct-badges { margin-top:8px; }
  .ct-details { flex-direction:column; gap:4px; }
}
</style>

<div class="ct-header">
    <h2><i class="bi bi-person-lines-fill"></i>Mis Contactos</h2>
</div>

<p class="ct-desc">
    Gestiona tus contactos de registro de dominios. Los cambios que realices aqui afectaran a todos los dominios asociados a cada contacto.
</p>

<?php if (empty($contacts)): ?>
<div class="ct-empty">
    <i class="bi bi-person-x"></i>
    <h4>No tienes contactos guardados</h4>
    <p>Los contactos se crean automaticamente cuando registras un dominio.</p>
    <a href="/customer/register-domain" class="ct-empty-btn">
        <i class="bi bi-globe"></i>Registrar un Dominio
    </a>
</div>
<?php else: ?>

<?php foreach ($contacts as $contact): ?>
<div class="ct-card <?= $contact['is_default'] ? 'ct-default' : '' ?>"
     data-id="<?= $contact['id'] ?>"
     data-firstname="<?= htmlspecialchars($contact['first_name'] ?? '', ENT_QUOTES) ?>"
     data-lastname="<?= htmlspecialchars($contact['last_name'] ?? '', ENT_QUOTES) ?>"
     data-company="<?= htmlspecialchars($contact['company'] ?? '', ENT_QUOTES) ?>"
     data-email="<?= htmlspecialchars($contact['email'] ?? '', ENT_QUOTES) ?>"
     data-phone="<?= htmlspecialchars($contact['phone'] ?? '', ENT_QUOTES) ?>"
     data-street="<?= htmlspecialchars($contact['address_street'] ?? '', ENT_QUOTES) ?>"
     data-number="<?= htmlspecialchars($contact['address_number'] ?? '', ENT_QUOTES) ?>"
     data-zipcode="<?= htmlspecialchars($contact['address_zipcode'] ?? '', ENT_QUOTES) ?>"
     data-city="<?= htmlspecialchars($contact['address_city'] ?? '', ENT_QUOTES) ?>"
     data-state="<?= htmlspecialchars($contact['address_state'] ?? '', ENT_QUOTES) ?>"
     data-country="<?= htmlspecialchars($contact['address_country'] ?? '', ENT_QUOTES) ?>"
     data-domains="<?= (int)($contact['domains_count'] ?? 0) ?>">
    <div class="ct-card-top">
        <div class="ct-info">
            <div class="ct-name"><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></div>
            <?php if (!empty($contact['company'])): ?>
            <div class="ct-company"><i class="bi bi-building"></i><?= htmlspecialchars($contact['company']) ?></div>
            <?php endif; ?>
            <div class="ct-details">
                <span><i class="bi bi-envelope"></i><?= htmlspecialchars($contact['email']) ?></span>
                <span><i class="bi bi-telephone"></i><?= htmlspecialchars($contact['phone']) ?></span>
                <?php if (!empty($contact['address_city']) || !empty($contact['address_country'])): ?>
                <span><i class="bi bi-geo-alt"></i><?= htmlspecialchars(trim(($contact['address_city'] ?? '') . ', ' . ($contact['address_country'] ?? ''), ', ')) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ct-badges">
            <?php if ($contact['is_default']): ?>
            <span class="ct-badge-default"><i class="bi bi-star-fill"></i>Predeterminado</span>
            <?php endif; ?>
            <?php if (($contact['domains_count'] ?? 0) > 0): ?>
            <span class="ct-badge-domains"><i class="bi bi-globe2"></i><?= $contact['domains_count'] ?> dominio<?= $contact['domains_count'] > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ct-actions">
        <button type="button" class="ct-btn ct-btn-edit" onclick="editContact(<?= $contact['id'] ?>)">
            <i class="bi bi-pencil"></i>Editar
        </button>
        <?php if (!$contact['is_default']): ?>
        <button type="button" class="ct-btn ct-btn-default" onclick="setDefault(<?= $contact['id'] ?>)">
            <i class="bi bi-star"></i>Predeterminado
        </button>
        <?php endif; ?>
        <?php if (($contact['domains_count'] ?? 0) == 0): ?>
        <button type="button" class="ct-btn ct-btn-delete" onclick="deleteContact(<?= $contact['id'] ?>)">
            <i class="bi bi-trash"></i>Eliminar
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<script>
var csrfToken = '<?= $csrf_token ?>';

function editContact(id) {
    var card = document.querySelector('.ct-card[data-id="' + id + '"]');
    if (!card) return;

    var fn = card.dataset.firstname || '';
    var ln = card.dataset.lastname || '';
    var co = card.dataset.company || '';
    var em = card.dataset.email || '';
    var ph = card.dataset.phone || '';

    Swal.fire({
        title: '<i class="bi bi-pencil-square" style="margin-right:6px;color:#4e73df"></i> Editar Contacto',
        html:
            '<div style="text-align:left;font-size:0.82rem;">' +
            '<label style="display:block;font-weight:600;color:#243141;margin-bottom:3px;">Nombre</label>' +
            '<input id="swal-fn" style="width:100%;padding:7px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:0.82rem;margin-bottom:10px;box-sizing:border-box;" value="' + fn.replace(/"/g, '&quot;') + '">' +
            '<label style="display:block;font-weight:600;color:#243141;margin-bottom:3px;">Apellidos</label>' +
            '<input id="swal-ln" style="width:100%;padding:7px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:0.82rem;margin-bottom:10px;box-sizing:border-box;" value="' + ln.replace(/"/g, '&quot;') + '">' +
            '<label style="display:block;font-weight:600;color:#243141;margin-bottom:3px;">Empresa</label>' +
            '<input id="swal-co" style="width:100%;padding:7px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:0.82rem;margin-bottom:10px;box-sizing:border-box;" value="' + co.replace(/"/g, '&quot;') + '">' +
            '<label style="display:block;font-weight:600;color:#243141;margin-bottom:3px;">Email</label>' +
            '<input id="swal-em" type="email" style="width:100%;padding:7px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:0.82rem;margin-bottom:10px;box-sizing:border-box;" value="' + em.replace(/"/g, '&quot;') + '">' +
            '<label style="display:block;font-weight:600;color:#243141;margin-bottom:3px;">Telefono</label>' +
            '<input id="swal-ph" style="width:100%;padding:7px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:0.82rem;margin-bottom:10px;box-sizing:border-box;" value="' + ph.replace(/"/g, '&quot;') + '">' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg" style="margin-right:6px"></i>Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#8a94a6',
        focusConfirm: false,
        customClass: { popup: 'swal-edit-popup' },
        preConfirm: function() {
            var firstName = document.getElementById('swal-fn').value.trim();
            var lastName = document.getElementById('swal-ln').value.trim();
            var email = document.getElementById('swal-em').value.trim();
            if (!firstName || !lastName || !email) {
                Swal.showValidationMessage('Nombre, Apellidos y Email son obligatorios');
                return false;
            }
            return {
                first_name: firstName,
                last_name: lastName,
                company: document.getElementById('swal-co').value.trim(),
                email: email,
                phone: document.getElementById('swal-ph').value.trim()
            };
        }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var fd = new FormData();
            fd.append('_csrf_token', csrfToken);
            fd.append('first_name', result.value.first_name);
            fd.append('last_name', result.value.last_name);
            fd.append('company', result.value.company);
            fd.append('email', result.value.email);
            fd.append('phone', result.value.phone);

            fetch('/customer/contacts/' + id + '/update', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon:'success', title:'Contacto actualizado', text: data.message || 'Cambios guardados correctamente.', confirmButtonColor:'#4e73df' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo actualizar el contacto.', 'error');
                    }
                })
                .catch(function() { Swal.fire('Error', 'Error de conexion.', 'error'); });
        }
    });
}

function setDefault(id) {
    Swal.fire({
        title: 'Establecer como predeterminado',
        text: 'Este contacto se usara por defecto al registrar nuevos dominios.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: 'Si, establecer',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('_csrf_token', csrfToken);
            fetch('/customer/contacts/' + id + '/set-default', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon:'success', title:'Listo', text: data.message || 'Contacto predeterminado actualizado.', confirmButtonColor:'#4e73df' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo establecer.', 'error');
                    }
                })
                .catch(function() { Swal.fire('Error', 'Error de conexion.', 'error'); });
        }
    });
}

function deleteContact(id) {
    Swal.fire({
        title: 'Eliminar contacto?',
        text: 'Esta accion no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('_csrf_token', csrfToken);
            fetch('/customer/contacts/' + id + '/delete', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon:'success', title:'Eliminado', text: data.message || 'Contacto eliminado.', confirmButtonColor:'#4e73df' })
                            .then(function() { location.reload(); });
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo eliminar.', 'error');
                    }
                })
                .catch(function() { Swal.fire('Error', 'Error de conexion.', 'error'); });
        }
    });
}
</script>
@endsection
