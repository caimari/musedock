@extends('Customer.layout')

@section('panel_content')
@php
  $cpFirstName = trim(explode(' ', (string) ($customer['name'] ?? ''))[0] ?? '');
  $cpGate = fn (string $c) => class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($c);
@endphp
<style>
  .mdp-dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; margin-bottom: 24px; }
  .mdp-hello h2 { font-size: 2rem; font-weight: 800; color: #111827; margin: 0 0 6px; letter-spacing: -.01em; }
  .mdp-hello p { color: #64748b; font-size: 1rem; margin: 0; }
  .mdp-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 28px; }
  .mdp-tile { border-radius: 16px; padding: 22px 22px 20px; color: #fff; position: relative; overflow: hidden; min-height: 132px; }
  .mdp-tile .mdp-tile-label { font-size: 1rem; font-weight: 600; opacity: .95; }
  .mdp-tile .mdp-tile-num { font-size: 2.3rem; font-weight: 800; line-height: 1.1; margin-top: 6px; }
  .mdp-tile .mdp-tile-sub { font-size: .85rem; opacity: .85; margin-top: 2px; }
  .mdp-tile i { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 3rem; opacity: .9; }
  .mdp-tile-blue   { background: linear-gradient(135deg, #2f6bf2, #1d4fd0); }
  .mdp-tile-teal   { background: linear-gradient(135deg, #0f8a83, #0b6e69); }
  .mdp-tile-indigo { background: linear-gradient(135deg, #5b4fe0, #4338ca); }
  .mdp-quick a { display: flex; align-items: center; gap: 14px; padding: 14px 4px; border-bottom: 1px solid #eef1f6; color: #1f2937; text-decoration: none; font-weight: 500; }
  .mdp-quick a:last-child { border-bottom: 0; }
  .mdp-quick a .mdp-q-icon { width: 38px; height: 38px; border-radius: 10px; background: var(--cp-accent-soft); color: var(--cp-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; flex: 0 0 38px; }
  .mdp-quick a .bi-chevron-right { margin-left: auto; color: #94a3b8; }
  .mdp-quick a:hover { color: var(--cp-accent); }
  .mdp-list-row { padding: 18px 0; border-top: 1px solid #eef1f6; }
  .mdp-list-row:first-of-type { border-top: 0; padding-top: 4px; }
  .mdp-list-row:target { background: var(--cp-accent-soft); border-radius: 12px; padding-left: 14px; padding-right: 14px; scroll-margin-top: 96px; }
  .mdp-empty { background: #f5f8ff; border-radius: 12px; padding: 16px 18px; font-size: .92rem; color: #1e3a8a; }
  @media (max-width: 1200px) { .mdp-dash-grid { grid-template-columns: 1fr; } }
  @media (max-width: 700px) { .mdp-tiles { grid-template-columns: 1fr; } .mdp-hello h2 { font-size: 1.5rem; } }
</style>

<div class="mdp-dash-grid">
  {{-- Bienvenida + cifras --}}
  <section class="mdp-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
      <div class="mdp-hello">
        <h2>¡Hola{{ $cpFirstName !== '' ? ' ' . $cpFirstName : '' }}! <span aria-hidden="true">👋</span></h2>
        <p>Bienvenido de nuevo a tu panel de {{ site_setting('site_name', 'MuseDock') }}</p>
      </div>
      <a href="/customer/profile" class="mdp-btn-outline">Gestionar cuenta</a>
    </div>
    <div class="mdp-tiles">
      <div class="mdp-tile mdp-tile-blue">
        <div class="mdp-tile-label">Sitios</div>
        <div class="mdp-tile-num"><?= (int) ($stats['total_tenants'] ?? 0) ?></div>
        <div class="mdp-tile-sub">en total</div>
        <i class="bi bi-globe2" aria-hidden="true"></i>
      </div>
      <div class="mdp-tile mdp-tile-teal">
        <div class="mdp-tile-label">Activos</div>
        <div class="mdp-tile-num"><?= (int) ($stats['active_tenants'] ?? 0) ?></div>
        <div class="mdp-tile-sub">funcionando</div>
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
      </div>
      <div class="mdp-tile mdp-tile-indigo">
        <div class="mdp-tile-label">Cloudflare</div>
        <div class="mdp-tile-num"><?= (int) ($stats['cloudflare_protected'] ?? 0) ?></div>
        <div class="mdp-tile-sub">protegidos</div>
        <i class="bi bi-shield-check" aria-hidden="true"></i>
      </div>
    </div>
  </section>

  {{-- Accesos rápidos --}}
  <aside class="mdp-card">
    <div class="mdp-card-head">
      <h3 class="mdp-card-title"><span class="mdp-icon-box" style="background:linear-gradient(135deg,#2f6bf2,#1d4fd0);"><i class="bi bi-lightning-charge" aria-hidden="true"></i></span> Acceso rápido</h3>
    </div>
    <nav class="mdp-quick" aria-label="Acceso rápido">
      @if($cpGate('free_subdomain'))<a href="/customer/request-free-subdomain"><span class="mdp-q-icon"><i class="bi bi-globe2" aria-hidden="true"></i></span>Crear sitio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      @if($cpGate('connect_domain'))<a href="/customer/request-custom-domain"><span class="mdp-q-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>Conectar dominio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      @if($cpGate('register_domain'))<a href="/customer/register-domain"><span class="mdp-q-icon"><i class="bi bi-cart-plus" aria-hidden="true"></i></span>Registrar dominio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      <a href="/customer/contacts"><span class="mdp-q-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>Contactos<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/customer/tenant-admins"><span class="mdp-q-icon"><i class="bi bi-people" aria-hidden="true"></i></span>Administradores<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/docs/"><span class="mdp-q-icon"><i class="bi bi-book" aria-hidden="true"></i></span>Documentación<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/soporte"><span class="mdp-q-icon"><i class="bi bi-life-preserver" aria-hidden="true"></i></span>Soporte<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </nav>
  </aside>
</div>

{{-- My Sites --}}
<section class="mdp-card" id="mdp-sites" style="margin-bottom:24px;">
<div class="mdp-card-head">
  <h3 class="mdp-card-title"><span class="mdp-icon-box" style="background:linear-gradient(135deg,#0f8a83,#0b6e69);"><i class="bi bi-window-stack" aria-hidden="true"></i></span> Mis sitios</h3>
</div>

<?php if (empty($tenants)): ?>
<div class="mdp-empty">
  <i class="bi bi-info-circle" style="margin-right:6px;"></i>
  Aún no tienes sitios web. Solicita tu primer subdominio FREE o incorpora tu dominio personalizado!
</div>
<?php else: ?>
  <?php foreach ($tenants as $tenant): ?>
  <?php
    // Calculate needs_retry without health check
    $isCustomDomain = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']);
    $tenant['needs_retry'] = $isCustomDomain
        ? ($tenant['status'] === 'waiting_ns_change' || !$tenant['caddy_route_id'] || ($tenant['caddy_status'] ?? '') !== 'active')
        : (!($tenant['cloudflare_record_id'] ?? null) || !$tenant['caddy_route_id'] || ($tenant['caddy_status'] ?? '') !== 'active');
  ?>
  <div class="mdp-list-row" id="site-<?= (int) $tenant['id'] ?>">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
      <i class="bi bi-globe" style="color:var(--cp-accent);"></i>
      <a href="https://<?= htmlspecialchars($tenant['domain']) ?>" target="_blank" style="color:var(--cp-accent); font-weight:700; font-size:1.05rem; text-decoration:none;">
        <?= htmlspecialchars($tenant['domain']) ?>
      </a>
    </div>

    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px;">
      <span style="font-size:0.7rem; padding:3px 8px; border-radius:4px; font-weight:600;
        background:<?= strtolower($tenant['plan'] ?? '') === 'free' ? '#d1fae5' : '#e0e7ff' ?>;
        color:<?= strtolower($tenant['plan'] ?? '') === 'free' ? '#065f46' : '#3730a3' ?>;">
        Plan <?= strtoupper($tenant['plan'] ?? 'FREE') ?>
      </span>
      <?php
        $statusColors = ['active' => ['#d1fae5','#065f46'], 'pending' => ['#fef3c7','#92400e'], 'waiting_ns_change' => ['#fef3c7','#92400e'], 'error' => ['#fee2e2','#991b1b'], 'suspended' => ['#f3f4f6','#374151']];
        $sc = $statusColors[$tenant['status'] ?? 'active'] ?? ['#f3f4f6','#374151'];
        $statusLabels = ['active' => 'Activo', 'pending' => 'Pendiente', 'waiting_ns_change' => 'Esperando DNS', 'error' => 'Error', 'suspended' => 'Suspendido'];
      ?>
      <span style="font-size:0.7rem; padding:3px 8px; border-radius:4px; font-weight:600; background:<?= $sc[0] ?>; color:<?= $sc[1] ?>;">
        <?= $statusLabels[$tenant['status'] ?? 'active'] ?? ucfirst($tenant['status'] ?? '') ?>
      </span>
      <?php if (!empty($tenant['cloudflare_proxied'])): ?>
      <span style="font-size:0.7rem; padding:3px 8px; border-radius:4px; font-weight:600; background:#fef3c7; color:#92400e;">
        <i class="bi bi-shield-fill-check"></i> Cloudflare
      </span>
      <?php endif; ?>
      <?php if (!empty($tenant['is_subdomain'])): ?>
      <span style="font-size:0.7rem; padding:3px 8px; border-radius:4px; font-weight:600; background:#e0f2fe; color:#0369a1;">
        Subdominio
      </span>
      <?php endif; ?>
      <?php if (isset($tenant['health_badge'])): ?>
      <span style="font-size:0.7rem; padding:3px 8px; border-radius:4px; font-weight:600;
        background:<?= $tenant['health_status'] === 'healthy' ? '#d1fae5' : ($tenant['health_status'] === 'degraded' ? '#fef3c7' : '#fee2e2') ?>;
        color:<?= $tenant['health_status'] === 'healthy' ? '#065f46' : ($tenant['health_status'] === 'degraded' ? '#92400e' : '#991b1b') ?>;"
        title="<?= htmlspecialchars(implode(', ', $tenant['health_check']['errors'] ?? [])) ?>">
        <?= $tenant['health_badge'] ?>
      </span>
      <?php endif; ?>
    </div>

    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <?php if ($tenant['status'] === 'active'): ?>
      <a href="https://<?= htmlspecialchars($tenant['domain']) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
         target="_blank" style="display:inline-flex; align-items:center; gap:4px; padding:5px 12px; background:var(--cp-accent); color:#fff; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none;">
        <i class="bi bi-gear"></i> Admin
      </a>
      <a href="https://<?= htmlspecialchars($tenant['domain']) ?>" target="_blank"
         style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; font-weight:500; text-decoration:none;">
        <i class="bi bi-eye"></i> Ver Sitio
      </a>
      <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
      <a href="/customer/tenant/<?= $tenant['id'] ?>/dns-email"
         style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; font-weight:500; text-decoration:none;">
        <i class="bi bi-envelope-at"></i> DNS / Email
      </a>
      <?php endif; ?>
      <button onclick="runHealthCheck(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')"
              style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; font-weight:500; background:#fff; cursor:pointer;">
        <i class="bi bi-heart-pulse"></i> Verificar
      </button>
      <button onclick="toggleAliasPanel(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')"
              style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; font-weight:500; background:#fff; cursor:pointer;">
        <i class="bi bi-link-45deg"></i> Alias
      </button>
      <?php elseif ($tenant['status'] === 'waiting_ns_change'): ?>
      <span style="font-size:0.8rem; color:#f59e0b;"><i class="bi bi-hourglass-split"></i> Esperando cambio de nameservers...</span>
      <?php if (!empty($tenant['cloudflare_zone_id'])): ?>
      <a href="/customer/tenant/<?= $tenant['id'] ?>/dns-email"
         style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; font-weight:500; text-decoration:none;">
        <i class="bi bi-envelope-at"></i> Gestionar
      </a>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (isset($tenant['needs_retry']) && $tenant['needs_retry']): ?>
        <?php $isCustom = !empty($tenant['cloudflare_zone_id']) && empty($tenant['is_subdomain']); $isWaiting = ($tenant['status'] ?? '') === 'waiting_ns_change'; ?>
        <button onclick="retryProvisioning(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['domain']) ?>')"
                style="display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border:1px solid <?= ($isCustom && $isWaiting) ? '#60a5fa' : '#fbbf24' ?>; color:<?= ($isCustom && $isWaiting) ? '#1d4ed8' : '#92400e' ?>; border-radius:6px; font-size:0.78rem; font-weight:500; background:#fff; cursor:pointer;">
          <i class="bi bi-<?= ($isCustom && $isWaiting) ? 'check2-circle' : 'arrow-clockwise' ?>"></i>
          <?= ($isCustom && $isWaiting) ? 'Verificar NS' : 'Reintentar' ?>
        </button>
      <?php endif; ?>
    </div>

    {{-- Alias panel (hidden) --}}
    <div class="alias-panel" id="alias-panel-<?= $tenant['id'] ?>" style="display:none; margin-top:12px; padding-top:12px; border-top:1px solid #edf0f5;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <strong style="font-size:0.82rem;"><i class="bi bi-link-45deg"></i> Alias de Dominio</strong>
        <span style="font-size:0.7rem; padding:2px 8px; background:#f3f4f6; border-radius:4px;" id="alias-count-<?= $tenant['id'] ?>">...</span>
      </div>
      <div id="alias-list-<?= $tenant['id'] ?>" style="margin-bottom:8px;">
        <span style="font-size:0.8rem; color:#8a94a6;">Cargando...</span>
      </div>
      <div style="display:flex; gap:6px; align-items:center;">
        <input type="text" id="alias-input-<?= $tenant['id'] ?>" placeholder="midominio.com"
               style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.8rem; width:180px;"
               onkeypress="if(event.key==='Enter'){event.preventDefault();submitAlias(<?= $tenant['id'] ?>)}">
        <label style="font-size:0.75rem; color:#6b7280; display:flex; align-items:center; gap:4px;">
          <input type="checkbox" id="alias-www-<?= $tenant['id'] ?>" checked> www
        </label>
        <button onclick="submitAlias(<?= $tenant['id'] ?>)" style="padding:6px 12px; background:#4e73df; color:#fff; border:none; border-radius:6px; font-size:0.78rem; cursor:pointer;">Añadir</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</section>

{{-- My Registered Domains --}}
<section class="mdp-card" style="margin-bottom:24px;">
<div class="mdp-card-head">
  <h3 class="mdp-card-title"><span class="mdp-icon-box" style="background:linear-gradient(135deg,#5b4fe0,#4338ca);"><i class="bi bi-globe-americas" aria-hidden="true"></i></span> Mis dominios registrados</h3>
</div>

<?php if (empty($domainOrders)): ?>
<div class="mdp-empty">
  <i class="bi bi-info-circle" style="margin-right:6px;"></i>
  Aún no tienes dominios registrados. Puedes registrar uno nuevo o transferir un dominio existente.
</div>
<?php else: ?>
  <?php foreach ($domainOrders as $order): ?>
  <div class="mdp-list-row">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
      <div>
        <span style="font-weight:600; color:#243141; font-size:0.9rem;"><?= htmlspecialchars($order['domain'] ?? '') ?>.<?= htmlspecialchars($order['extension'] ?? '') ?></span>
        <?php
          $orderStatusColors = ['registered' => ['#d1fae5','#065f46'], 'active' => ['#d1fae5','#065f46'], 'pending' => ['#fef3c7','#92400e'], 'failed' => ['#fee2e2','#991b1b']];
          $osc = $orderStatusColors[$order['status'] ?? 'pending'] ?? ['#f3f4f6','#374151'];
        ?>
        <span style="font-size:0.7rem; padding:2px 8px; border-radius:4px; font-weight:600; background:<?= $osc[0] ?>; color:<?= $osc[1] ?>; margin-left:6px;">
          <?= ucfirst($order['status'] ?? 'pending') ?>
        </span>
      </div>
      <div style="display:flex; gap:6px;">
        <?php if (in_array($order['status'] ?? '', ['registered', 'active'])): ?>
        <a href="/customer/domain/<?= $order['id'] ?>/manage" style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; text-decoration:none;">
          <i class="bi bi-sliders"></i> Gestionar
        </a>
        <a href="/customer/domain/<?= $order['id'] ?>/dns" style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #d5dce8; color:#334155; border-radius:8px; font-size:0.82rem; text-decoration:none;">
          <i class="bi bi-hdd-network"></i> DNS
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</section>

<script>
function toggleAliasPanel(tenantId, domain) {
  var panel = document.getElementById('alias-panel-' + tenantId);
  if (!panel) return;
  var isHidden = panel.style.display === 'none';
  panel.style.display = isHidden ? 'block' : 'none';
  if (isHidden) loadAliases(tenantId);
}

function loadAliases(tenantId) {
  var list = document.getElementById('alias-list-' + tenantId);
  var count = document.getElementById('alias-count-' + tenantId);
  fetch('/customer/tenant/' + tenantId + '/aliases')
    .then(r => r.json())
    .then(data => {
      count.textContent = (data.aliases || []).length;
      if (!data.aliases || data.aliases.length === 0) {
        list.innerHTML = '<span style="font-size:0.8rem;color:#8a94a6;">Sin alias configurados</span>';
        return;
      }
      list.innerHTML = data.aliases.map(a =>
        '<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:0.8rem;">' +
          '<span>' + (a.domain || a.alias_domain) + '</span>' +
          '<button onclick="removeAlias(' + tenantId + ',\'' + (a.domain || a.alias_domain) + '\',' + parseInt(a.id, 10) + ')" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:0.75rem;"><i class="bi bi-x-circle"></i></button>' +
        '</div>'
      ).join('');
    })
    .catch(() => { list.innerHTML = '<span style="font-size:0.8rem;color:#dc3545;">Error al cargar</span>'; });
}

function submitAlias(tenantId) {
  var input = document.getElementById('alias-input-' + tenantId);
  var www = document.getElementById('alias-www-' + tenantId);
  if (!input.value.trim()) return;
  // Auditoría 2026-10-05: el servidor lee $_POST; con JSON no llegaban ni el dominio ni el token
  fetch('/customer/tenant/' + tenantId + '/aliases/add', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams({ domain: input.value.trim(), include_www: www.checked ? '1' : '', _csrf_token: '<?= csrf_token() ?>' })
  }).then(r => r.json()).then(data => {
    if (data.success) { input.value = ''; loadAliases(tenantId); Swal.fire({icon:'success',title:'Alias añadido',toast:true,position:'top-end',timer:2000,showConfirmButton:false}); }
    else Swal.fire({icon:'error',title:'Error',text:data.error||'No se pudo añadir'});
  });
}

function removeAlias(tenantId, domain, aliasId) {
  Swal.fire({title:'¿Eliminar alias?',text:domain,icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Eliminar',cancelButtonText:'Cancelar'}).then(r => {
    if (r.isConfirmed) {
      fetch('/customer/tenant/' + tenantId + '/aliases/remove', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({ alias_id: String(aliasId || ''), _csrf_token: '<?= csrf_token() ?>' })
      }).then(r => r.json()).then(data => {
        if (data.success) loadAliases(tenantId);
        else Swal.fire({icon:'error',title:'Error',text:data.error||'No se pudo eliminar'});
      });
    }
  });
}

function runHealthCheck(tenantId, domain) {
  Swal.fire({title:'Verificando ' + domain + '...',allowOutsideClick:false,didOpen:function(){Swal.showLoading()}});
  fetch('/customer/tenant/' + tenantId + '/health-check').then(function(r){return r.json()}).then(function(data) {
    if (!data.success) { Swal.fire({icon:'error',title:'Error',text:data.error||'No se pudo verificar'}); return; }
    var hc = data.health_check || {};
    var status = hc.overall_status || 'unknown';
    var isOk = status === 'healthy';
    var checks = hc.checks || {};
    var details = '<div style="margin-bottom:8px;">';
    var labels = {dns:'DNS', http:'HTTP/HTTPS', ssl:'Certificado SSL', cloudflare:'Cloudflare Proxy'};
    for (var k in checks) {
      var c = checks[k];
      var passed = c.passed === true;
      details += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:0.85rem;">' +
        '<span style="color:#243141;">' + (labels[k] || k) + '</span>' +
        '<span style="color:' + (passed ? '#28a745' : '#dc3545') + ';font-weight:600;">' +
          (passed ? '<i class="bi bi-check-circle-fill"></i> OK' : '<i class="bi bi-x-circle-fill"></i> Error') +
        '</span></div>';
    }
    details += '</div>';
    // Show errors/warnings
    var msgs = (hc.errors || []).concat(hc.warnings || []);
    if (msgs.length > 0) {
      details += '<div style="margin-top:8px;padding:8px 12px;background:#fff3cd;border-radius:6px;font-size:0.8rem;color:#856404;">';
      msgs.forEach(function(m) { details += '<div style="padding:2px 0;">' + m + '</div>'; });
      details += '</div>';
    }
    Swal.fire({
      icon: isOk ? 'success' : (status === 'degraded' ? 'warning' : 'error'),
      title: isOk ? 'Todo correcto' : (status === 'degraded' ? 'Parcialmente operativo' : 'Problemas detectados'),
      html: details,
      showConfirmButton: true,
      confirmButtonColor: '#4e73df'
    });
  }).catch(function() { Swal.fire({icon:'error',title:'Error',text:'No se pudo verificar'}); });
}

function retryProvisioning(tenantId, domain) {
  Swal.fire({title:'Reintentando provisioning...',text:domain,allowOutsideClick:false,didOpen:()=>{Swal.showLoading()}});
  fetch('/customer/tenant/' + tenantId + '/retry', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ _csrf_token: '<?= csrf_token() ?>' })
  }).then(r => r.json()).then(data => {
    if (data.success) { Swal.fire({icon:'success',title:'Completado',text:data.message||''}).then(()=>location.reload()); }
    else Swal.fire({icon:'error',title:'Error',text:data.error||'No se pudo completar'});
  }).catch(() => { Swal.fire({icon:'error',title:'Error',text:'Error de conexión'}); });
}
</script>
@endsection
