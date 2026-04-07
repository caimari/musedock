@extends('Customer.layout')

@section('panel_content')
<div style="">
  {{-- Header --}}
  <div style="text-align:center; margin-bottom:20px; padding:20px; background:linear-gradient(135deg,#eef2ff,#dbeafe); border-radius:10px;">
    <h2 style="font-size:1.1rem; font-weight:700; color:#243141; margin:0 0 4px;">
      <i class="bi bi-link-45deg" style="margin-right:6px; color:#4e73df;"></i> Vincular Dominio Existente
    </h2>
    <p style="font-size:0.82rem; color:#6b7280; margin:0;">Conecta tu dominio a MuseDock cambiando solo los nameservers</p>
  </div>

  {{-- How it works --}}
  <div style="background:#eef2ff; border-left:3px solid #4e73df; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
    <div style="font-size:0.85rem; font-weight:600; color:#243141; margin-bottom:6px;">
      <i class="bi bi-info-circle" style="margin-right:6px; color:#4e73df;"></i> Como funciona (solo cambio de DNS)
    </div>
    <ul style="list-style:none; padding:0; margin:0; font-size:0.82rem; color:#4a5568;">
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#4e73df; margin-right:6px;"></i> <strong>Tu dominio sigue siendo tuyo</strong> — no se transfiere, solo cambias los nameservers</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#4e73df; margin-right:6px;"></i> Tu dominio sera protegido por Cloudflare automaticamente (SSL gratuito)</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#4e73df; margin-right:6px;"></i> Recibiras instrucciones para cambiar los nameservers en tu registrador</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#4e73df; margin-right:6px;"></i> Tu sitio se activara automaticamente cuando detectemos el cambio de DNS</li>
    </ul>
  </div>

  {{-- Important warning --}}
  <div style="background:#fff8ed; border-left:3px solid #e67e22; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
    <div style="font-size:0.85rem; font-weight:600; color:#243141; margin-bottom:4px;">
      <i class="bi bi-exclamation-triangle" style="margin-right:6px; color:#e67e22;"></i> Importante
    </div>
    <p style="font-size:0.82rem; color:#4a5568; margin:0 0 4px;">Debes ser el propietario del dominio y tener acceso para cambiar los nameservers en tu registrador (GoDaddy, Namecheap, Google Domains, etc.)</p>
    <p style="font-size:0.75rem; color:#8a94a6; margin:0;">
      <i class="bi bi-shield-check" style="margin-right:4px;"></i> El dominio permanece registrado en tu proveedor actual — <strong>no es una transferencia</strong>.
    </p>
  </div>

  {{-- Form --}}
  <form id="customDomainForm" onsubmit="submitRequest(event)">
    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

    {{-- Domain input --}}
    <div style="margin-bottom:16px;">
      <label style="display:block; font-size:0.82rem; font-weight:600; color:#243141; margin-bottom:6px;">Tu Dominio</label>
      <div style="display:flex; align-items:stretch;">
        <span style="padding:10px 14px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px 0 0 8px; border-right:none; display:flex; align-items:center; color:#8a94a6;">
          <i class="bi bi-globe"></i>
        </span>
        <input type="text" name="domain" id="domainInput" placeholder="tudominio.com"
               pattern="^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$" required
               style="flex:1; padding:10px 12px; border:1px solid #d1d5db; border-radius:0 8px 8px 0; font-size:0.88rem; outline:none;"
               onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
      </div>
      <div style="font-size:0.75rem; color:#8a94a6; margin-top:4px;">Sin www, ejemplo: miempresa.com</div>
    </div>

    {{-- Email Routing checkbox --}}
    <div style="margin-bottom:16px;">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.82rem; color:#4a5568;">
        <input type="checkbox" name="enable_email_routing" id="enableEmailRouting" style="accent-color:#4e73df;">
        <span style="font-weight:600;">Habilitar Email Routing</span>
      </label>
      <div style="font-size:0.75rem; color:#8a94a6; margin-top:4px; padding-left:24px;">
        Los correos enviados a cualquier direccion de tu dominio seran redirigidos a <strong><?= htmlspecialchars($customer['email'] ?? '') ?></strong>
      </div>
      <div style="font-size:0.75rem; color:#8a94a6; margin-top:4px; padding-left:24px;">
        <i class="bi bi-lightbulb" style="margin-right:4px; color:#4e73df;"></i>
        <em>Puedes activar o configurar el Email Routing mas adelante desde tu panel de control.</em>
      </div>
    </div>

    {{-- Custom admin toggle --}}
    <div style="margin-bottom:20px;">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.82rem; color:#4a5568;">
        <input type="checkbox" id="customAdminToggle" onchange="toggleCustomAdmin()" style="accent-color:#4e73df;">
        <span style="font-weight:600;">Personalizar credenciales de admin</span>
      </label>
      <div style="font-size:0.75rem; color:#8a94a6; margin-top:4px; padding-left:24px;">
        <i class="bi bi-info-circle" style="margin-right:4px;"></i>
        Por defecto se generan credenciales automaticamente (admin@tudominio.com + password aleatorio). Puedes personalizarlas si lo prefieres.
      </div>

      <div id="customAdminFields" style="display:none; margin-top:12px; background:#f8fafc; border-radius:8px; padding:14px;">
        <div style="margin-bottom:10px;">
          <label style="display:block; font-size:0.78rem; font-weight:600; color:#4a5568; margin-bottom:4px;">Email del Admin</label>
          <input type="email" name="admin_email" id="adminEmailInput" placeholder="admin@ejemplo.com"
                 style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem; outline:none;">
        </div>
        <div>
          <label style="display:block; font-size:0.78rem; font-weight:600; color:#4a5568; margin-bottom:4px;">Password del Admin</label>
          <div style="position:relative;">
            <input type="password" name="admin_password" id="adminPasswordInput" placeholder="Minimo 8 caracteres" minlength="8"
                   style="width:100%; padding:8px 12px; padding-right:36px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem; outline:none;">
            <button type="button" onclick="togglePasswordVisibility()" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; cursor:pointer;">
              <i class="bi bi-eye" id="passwordToggleIcon"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- Buttons --}}
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button type="submit" style="display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:#4e73df; color:#fff; border:none; border-radius:7px; font-size:0.85rem; font-weight:600; cursor:pointer;">
        <i class="bi bi-link-45deg"></i> Vincular Dominio
      </button>
      <a href="/customer/dashboard" style="display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:1px solid #d1d5db; color:#4a5568; border-radius:7px; font-size:0.85rem; font-weight:500; text-decoration:none;">
        <i class="bi bi-arrow-left"></i> Volver al Dashboard
      </a>
    </div>
  </form>
</div>

<script>
function toggleCustomAdmin() {
  var f = document.getElementById('customAdminFields');
  var c = document.getElementById('customAdminToggle');
  f.style.display = c.checked ? 'block' : 'none';
  if (!c.checked) { document.getElementById('adminEmailInput').value = ''; document.getElementById('adminPasswordInput').value = ''; }
}

function togglePasswordVisibility() {
  var p = document.getElementById('adminPasswordInput');
  var i = document.getElementById('passwordToggleIcon');
  if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { p.type = 'password'; i.className = 'bi bi-eye'; }
}

function submitRequest(event) {
  event.preventDefault();
  var form = event.target;
  var formData = new FormData(form);
  var domain = formData.get('domain').toLowerCase().trim();

  if (!domain || domain.includes('musedock.com')) {
    Swal.fire({icon:'error',title:'Error',text:'Para subdominios de musedock.com usa "Solicitar Subdominio FREE"'});
    return;
  }
  if (!/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/.test(domain)) {
    Swal.fire({icon:'error',title:'Error',text:'Introduce un dominio valido (ejemplo: miempresa.com)'});
    return;
  }

  var customAdmin = document.getElementById('customAdminToggle').checked;
  if (customAdmin) {
    var email = formData.get('admin_email').trim();
    var pass = formData.get('admin_password');
    if (!email || !pass) { Swal.fire({icon:'error',title:'Error',text:'Completa email y password del admin'}); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { Swal.fire({icon:'error',title:'Error',text:'Email no valido'}); return; }
    if (pass.length < 8) { Swal.fire({icon:'error',title:'Error',text:'Password minimo 8 caracteres'}); return; }
  }

  Swal.fire({title:'Procesando solicitud...',html:'Estamos configurando tu dominio en Cloudflare...',allowOutsideClick:false,didOpen:function(){Swal.showLoading()}});

  fetch('/customer/request-custom-domain', {method:'POST',body:formData,headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(data) {
      if (data.success) {
        var nsHtml = '<div style="text-align:left;margin-top:12px;">';
        nsHtml += '<p style="font-size:0.85rem;font-weight:600;color:#243141;">Cambia los nameservers de tu dominio a:</p>';
        nsHtml += '<div style="background:#f8fafc;padding:10px;border-radius:6px;font-family:monospace;font-size:0.85rem;">';
        data.nameservers.forEach(function(ns, i) {
          nsHtml += '<div style="padding:2px 0;">NS'+(i+1)+': <strong>'+ns+'</strong></div>';
        });
        nsHtml += '</div>';

        if (data.admin_credentials && !customAdmin) {
          nsHtml += '<div style="margin-top:12px;padding:10px;background:#f0fdf4;border-radius:6px;font-size:0.85rem;">' +
            '<strong>Credenciales de Admin:</strong><br>' +
            'Email: <code>'+data.admin_credentials.email+'</code><br>' +
            'Password: <code>'+data.admin_credentials.password+'</code><br>' +
            '<span style="color:#8a94a6;font-size:0.75rem;">Guarda estas credenciales!</span></div>';
        }

        nsHtml += '<p style="margin-top:10px;font-size:0.75rem;color:#8a94a6;">Te hemos enviado un email con instrucciones detalladas.</p>';
        nsHtml += '</div>';

        Swal.fire({icon:'success',title:'Dominio Registrado!',html:data.message+nsHtml,confirmButtonColor:'#4e73df',confirmButtonText:'Ir al Dashboard'})
          .then(function(){window.location.href='/customer/dashboard'});
      } else {
        Swal.fire({icon:'error',title:'Error',text:data.error||'Ocurrio un error al procesar la solicitud'});
      }
    })
    .catch(function(){Swal.fire({icon:'error',title:'Error',text:'Error de conexion. Por favor intenta de nuevo.'})});
}
</script>
@endsection
