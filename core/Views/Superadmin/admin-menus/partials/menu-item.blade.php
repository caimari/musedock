<div class="menu-item" data-id="{{ $menu->id }}">
  <div class="menu-item-header">
    <div class="menu-item-drag-handle">
      <i class="bi bi-grip-vertical"></i>
    </div>

    <div class="menu-item-icon">
      @if($menu->icon)
        <i class="{{ $menu->icon_type === 'bi' ? 'bi bi-' . $menu->icon : $menu->icon_type . ' fa-' . $menu->icon }}"></i>
      @else
        <i class="bi bi-app"></i>
      @endif
    </div>

    <div class="menu-item-info">
      <h6 class="menu-item-title">
        {{ $menu->title }}

        @if(isset($menu->children) && count($menu->children) > 0)
          <span class="badge badge-parent">{{ count($menu->children) }} submenús</span>
        @endif

        @if(!$menu->is_active)
          <span class="badge badge-inactive">Inactivo</span>
        @endif
      </h6>
      <p class="menu-item-meta mb-0">
        <strong>URL:</strong> {{ $menu->url }}
        @if($menu->module_name)
          | <strong>Módulo:</strong> {{ $menu->module_name }}
        @endif
        | <strong>Slug:</strong> {{ $menu->slug }}
      </p>
    </div>
  </div>

  @if(isset($menu->children) && count($menu->children) > 0)
    <div class="menu-item-children">
      @foreach($menu->children as $child)
        @include('admin-menus.partials.menu-item', ['menu' => $child, 'level' => $level + 1])
      @endforeach
    </div>
  @endif
</div>
