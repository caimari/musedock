@php
  $active = $active ?? '';
  $items = [
    ['key' => 'general', 'label' => 'General', 'icon' => 'bi-gear', 'url' => route('settings')],
    ['key' => 'email', 'label' => 'Email (SMTP)', 'icon' => 'bi-envelope', 'url' => route('settings.email')],
    ['key' => 'seo', 'label' => 'SEO y Social', 'icon' => 'bi-search', 'url' => route('settings.seo')],
    ['key' => 'reading', 'label' => 'Lectura', 'icon' => 'bi-book', 'url' => route('settings.reading')],
    ['key' => 'cookies', 'label' => 'Cookies', 'icon' => 'bi-shield-check', 'url' => route('settings.cookies')],
    ['key' => 'storage', 'label' => 'Storage', 'icon' => 'bi-hdd', 'url' => route('settings.storage')],
    ['key' => 'advanced', 'label' => 'Avanzado', 'icon' => 'bi-sliders', 'url' => route('settings.advanced')],
    ['key' => 'api-keys', 'label' => 'API Keys', 'icon' => 'bi-key', 'url' => route('settings.api-keys')],
    ['key' => 'backups', 'label' => 'Backups', 'icon' => 'bi-database', 'url' => route('settings.backups')],
  ];
@endphp

<div class="card settings-side-nav">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-list me-1"></i>Opciones de Ajustes</h6>
  </div>
  <div class="list-group list-group-flush">
    @foreach($items as $item)
      <a href="{{ $item['url'] }}"
         class="list-group-item list-group-item-action d-flex align-items-center {{ $active === $item['key'] ? 'active' : '' }}">
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
</style>
