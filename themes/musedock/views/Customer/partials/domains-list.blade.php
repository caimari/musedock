{{-- Lista de dominios registrados del cliente (inicio y «Mis dominios»). Espera \$domainOrders. --}}
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
