@extends('Customer.layout')

@section('panel_content')
<div style="">
  {{-- Header --}}
  <div style="text-align:center; margin-bottom:20px; padding:20px; background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-radius:10px;">
    <h2 style="font-size:1.1rem; font-weight:700; color:#243141; margin:0 0 4px;">
      <i class="bi bi-gift" style="margin-right:6px; color:#28a745;"></i> Solicitar Subdominio FREE
    </h2>
    <p style="font-size:0.82rem; color:#6b7280; margin:0;">Obtiene tu sitio web gratuito en musedock.com</p>
  </div>

  {{-- Included features --}}
  <div style="background:#f0fdf4; border-left:3px solid #28a745; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
    <div style="font-size:0.85rem; font-weight:600; color:#243141; margin-bottom:6px;">
      <i class="bi bi-check-circle" style="margin-right:6px; color:#28a745;"></i> Incluido en tu plan FREE
    </div>
    <ul style="list-style:none; padding:0; margin:0; font-size:0.82rem; color:#4a5568;">
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#28a745; margin-right:6px;"></i> Subdominio gratuito: tuempresa.musedock.com</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#28a745; margin-right:6px;"></i> SSL automático incluido</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#28a745; margin-right:6px;"></i> Protección Cloudflare</li>
      <li style="padding:2px 0;"><i class="bi bi-check" style="color:#28a745; margin-right:6px;"></i> Activación instantánea</li>
    </ul>
  </div>

  {{-- Form --}}
  <form id="freeSubdomainForm" onsubmit="submitRequest(event)">
    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">

    {{-- Subdomain input --}}
    <div style="margin-bottom:16px;">
      <label style="display:block; font-size:0.82rem; font-weight:600; color:#243141; margin-bottom:6px;">Elige tu subdominio</label>
      <div style="display:flex; align-items:stretch;">
        <input type="text" name="subdomain" id="subdomainInput" placeholder="tuempresa"
               pattern="^[a-z0-9][a-z0-9-]{2,30}[a-z0-9]$" required
               oninput="updatePreview()"
               style="flex:1; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px 0 0 8px; font-size:0.9rem; border-right:none; outline:none;"
               onfocus="this.style.borderColor='#4e73df'" onblur="this.style.borderColor='#d1d5db'">
        <span style="padding:10px 14px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:0 8px 8px 0; font-size:0.85rem; color:#6b7280; white-space:nowrap; display:flex; align-items:center;">.musedock.com</span>
      </div>
      <div style="font-size:0.75rem; color:#8a94a6; margin-top:4px;">Solo letras minúsculas, números y guiones. Mínimo 4 caracteres.</div>
      <div id="subdomainPreview" style="background:#f8fafc; padding:10px; border-radius:6px; font-family:monospace; font-size:0.85rem; text-align:center; margin-top:8px; color:#4a5568;">
        <span style="color:#8a94a6;">tuempresa</span>.musedock.com
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
        Por defecto se generan credenciales automáticamente (admin@tusubdominio.musedock.com + password aleatorio). Puedes personalizarlas si lo prefieres.
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
            <input type="password" name="admin_password" id="adminPasswordInput" placeholder="Mínimo 8 caracteres" minlength="8"
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
      <button type="submit" style="display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:#28a745; color:#fff; border:none; border-radius:7px; font-size:0.85rem; font-weight:600; cursor:pointer;">
        <i class="bi bi-rocket-takeoff"></i> Crear mi Subdominio FREE
      </button>
      <a href="/customer/dashboard" style="display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:1px solid #d1d5db; color:#4a5568; border-radius:7px; font-size:0.85rem; font-weight:500; text-decoration:none;">
        <i class="bi bi-arrow-left"></i> Volver al Dashboard
      </a>
    </div>
  </form>
</div>

<script>
function updatePreview() {
  var input = document.getElementById('subdomainInput');
  var preview = document.getElementById('subdomainPreview');
  var v = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
  input.value = v;
  preview.innerHTML = '<strong>' + (v || '<span style="color:#8a94a6">tuempresa</span>') + '</strong>.musedock.com';
}

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
  var subdomain = formData.get('subdomain').toLowerCase().trim();

  if (subdomain.length < 4) { Swal.fire({icon:'error',title:'Error',text:'El subdominio debe tener al menos 4 caracteres'}); return; }
  if (!/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(subdomain)) { Swal.fire({icon:'error',title:'Error',text:'Solo letras minúsculas, números y guiones'}); return; }

  var customAdmin = document.getElementById('customAdminToggle').checked;
  if (customAdmin) {
    var email = formData.get('admin_email').trim();
    var pass = formData.get('admin_password');
    if (!email || !pass) { Swal.fire({icon:'error',title:'Error',text:'Completa email y password del admin'}); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { Swal.fire({icon:'error',title:'Error',text:'Email no válido'}); return; }
    if (pass.length < 8) { Swal.fire({icon:'error',title:'Error',text:'Password mínimo 8 caracteres'}); return; }
  }

  Swal.fire({title:'Procesando...',html:'Creando tu subdominio...',allowOutsideClick:false,didOpen:function(){Swal.showLoading()}});

  fetch('/customer/request-free-subdomain', {method:'POST',body:formData,headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(data) {
      if (data.success) {
        var html = 'Tu sitio web está listo en:<br><strong><a href="https://'+data.domain+'" target="_blank">'+data.domain+'</a></strong>';
        if (data.admin_credentials && !customAdmin) {
          html += '<div style="text-align:left;margin-top:12px;padding:10px;background:#f8fafc;border-radius:6px;font-size:0.85rem;">' +
            '<strong>Credenciales:</strong><br>Email: <code>'+data.admin_credentials.email+'</code><br>Password: <code>'+data.admin_credentials.password+'</code><br>' +
            '<span style="color:#8a94a6;font-size:0.75rem;">¡Guarda estas credenciales!</span></div>';
        }
        Swal.fire({icon:'success',title:'¡Subdominio creado!',html:html,confirmButtonColor:'#28a745',confirmButtonText:'Ir al Dashboard'})
          .then(function(){window.location.href='/customer/dashboard'});
      } else {
        Swal.fire({icon:'error',title:'Error',text:data.error||data.message||'No se pudo crear el subdominio'});
      }
    })
    .catch(function(){Swal.fire({icon:'error',title:'Error',text:'Error de conexión'})});
}
</script>
@endsection
