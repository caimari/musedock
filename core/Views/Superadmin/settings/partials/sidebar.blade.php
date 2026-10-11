@php
  $active = $active ?? '';
  $items = \Screenart\Musedock\Services\SettingsNavigation::items(false);
  try {
    $navigation = \Screenart\Musedock\Services\SettingsNavigation::synchronize(\Screenart\Musedock\Models\AdminMenu::getMenusWithCustomizations(null));
    foreach ($navigation as $menu) {
      if (($menu['slug'] ?? '') !== 'settings' && ($menu['title'] ?? '') !== 'Ajustes') continue;
      $items = [];
      foreach ($menu['children'] ?? [] as $key => $child) {
        if (function_exists('userCan')) {
          $auth = \Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser() ?? [];
          $root = ($auth['type'] ?? '') === 'super_admin' && !empty($auth['id']) && \Screenart\Musedock\Helpers\PermissionHelper::isSuperAdminRoot($auth['id']);
          $permission = $child['permission'] ?? null;
          if (!$root && ($permission === null || !userCan($permission))) continue;
        }
        $items[] = ['key' => $key, 'label' => $child['title'], 'icon' => $child['icon'] ?? 'bi-circle', 'url' => $child['url']];
      }
    }
  } catch (\Throwable $e) {
    // Keep the standard navigation when customized menus cannot be loaded.
  }

@endphp

<div class="card settings-side-nav">
  <div class="card-header">
    <button type="button" class="settings-nav-toggle" aria-expanded="true" aria-controls="settings-nav-links">
      <span><i class="bi bi-list me-1"></i>Opciones de Ajustes</span><i class="bi bi-chevron-up" aria-hidden="true"></i>
    </button>
  </div>
  <div id="settings-nav-links" class="list-group list-group-flush">
    @foreach($items as $item)
      <a href="{{ $item['url'] }}"
         class="list-group-item list-group-item-action d-flex align-items-center {{ ($active === $item['key'] || strtok($_SERVER['REQUEST_URI'] ?? '', '?') === $item['url']) ? 'active' : '' }}">
        <i class="bi {{ $item['icon'] }} me-2"></i>
        <span>{{ $item['label'] }}</span>
      </a>
    @endforeach
  </div>
</div>

<style>
  .settings-side-nav {
    position: sticky;
    top: 80px;
  }

  .settings-side-nav .list-group-item {
    border-left: 3px solid transparent;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
  }

  .settings-side-nav .list-group-item:hover {
    background: #f8f9fc;
    color: #1f2d3d;
    border-left-color: #d8deea;
  }

  .settings-side-nav .list-group-item.active,
  .settings-side-nav .list-group-item.active:hover,
  .settings-side-nav .list-group-item.active:focus {
    background: #e8f1ff !important;
    color: #1f4ea3 !important;
    border-color: #c8dafc !important;
    border-left-color: #4f89f7 !important;
  }

  .settings-side-nav .list-group-item.active i,
  .settings-side-nav .list-group-item.active:hover i,
  .settings-side-nav .list-group-item.active:focus i {
    color: #1f4ea3 !important;
  }
  .settings-nav-toggle { border: 0; padding: 0; background: transparent; width: 100%; display: flex; align-items: center; justify-content: space-between; color: inherit; text-align: left; }
  .settings-side-nav.is-folded #settings-nav-links { display: none; }
</style>
<script>
(function () {
  const card = document.querySelector('.settings-side-nav');
  if (!card) return;
  const button = card.querySelector('.settings-nav-toggle');
  const key = 'musedock:settings-nav-folded';
  function apply(folded) {
    card.classList.toggle('is-folded', folded);
    button.setAttribute('aria-expanded', String(!folded));
    button.querySelector('.bi-chevron-up, .bi-chevron-down').className = 'bi ' + (folded ? 'bi-chevron-down' : 'bi-chevron-up');
  }
  try { apply(localStorage.getItem(key) === '1'); } catch (_) {}
  button.addEventListener('click', function () {
    const folded = !card.classList.contains('is-folded');
    apply(folded);
    try { localStorage.setItem(key, folded ? '1' : '0'); } catch (_) {}
  });
})();
</script>
