{{-- Fila de menú con indentación según el nivel --}}
<tr data-id="{{ $menu->id }}" class="menu-row-level-{{ $level }}">
  <td>
    <div class="form-check form-switch">
      <input class="form-check-input toggle-active"
             type="checkbox"
             data-id="{{ $menu->id }}"
             {{ $menu->is_active ? 'checked' : '' }}>
    </div>
  </td>
  <td>
    @if($level > 0)
      <span class="text-muted" style="margin-left: {{ $level * 20 }}px;">
        <i class="bi bi-arrow-return-right"></i>
      </span>
    @endif
    <strong>{{ e($menu->title) }}</strong>
    @if($level > 0)
      <span class="badge bg-secondary ms-1" style="font-size: 0.7rem;">Submenú</span>
    @endif
  </td>
  <td><small class="text-muted">{{ $menu->slug }}</small></td>
  <td><small class="text-muted">{{ $menu->url }}</small></td>
  <td>
    @if($menu->icon)
      <i class="{{ $menu->icon_type === 'bi' ? 'bi' : $menu->icon_type }} {{ $menu->icon }}"></i>
      <small class="text-muted">{{ $menu->icon }}</small>
    @else
      <span class="text-muted">—</span>
    @endif
  </td>
  <td>
    @if($menu->module_name)
      <span class="badge bg-info">{{ $menu->module_name }}</span>
    @else
      <span class="text-muted">Sistema</span>
    @endif
  </td>
  <td>
    <span class="badge bg-secondary">{{ $menu->order_position }}</span>
  </td>
  <td>
    <a href="{{ route('tenant-menus.edit', ['id' => $menu->id]) }}" class="btn btn-sm btn-outline-primary" title="Editar">
      <i class="bi bi-pencil"></i>
    </a>
  </td>
</tr>

{{-- Renderizar children recursivamente --}}
@if(!empty($menu->children))
  @foreach($menu->children as $child)
    @include('tenant-menus.partials.menu-row', ['menu' => $child, 'level' => $level + 1])
  @endforeach
@endif
