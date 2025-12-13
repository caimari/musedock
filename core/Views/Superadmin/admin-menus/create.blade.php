@extends('layouts.app')

@section('title', $title ?? 'Crear Menú del Administrador')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Volver --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? 'Crear Menú del Administrador' }}</h2>
      <a href="{{ route('admin-menus.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
    </div>

    {{-- Formulario --}}
    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('admin-menus.store') }}" id="menuForm">
          @csrf

          <div class="row">
            {{-- Título --}}
            <div class="col-md-6 mb-3">
              <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="title" name="title" required>
            </div>

            {{-- Slug --}}
            <div class="col-md-6 mb-3">
              <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="slug" name="slug" required>
              <small class="text-muted">Identificador único (ej: my-custom-menu)</small>
            </div>
          </div>

          <div class="row">
            {{-- URL --}}
            <div class="col-md-6 mb-3">
              <label for="url" class="form-label">URL <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="url" name="url" required placeholder="{admin_path}/settings/reading">
              <small class="text-muted">Ruta del menú (ej: {admin_path}/settings/reading)</small>
            </div>

            {{-- Orden --}}
            <div class="col-md-3 mb-3">
              <label for="order_position" class="form-label">Posición de Orden</label>
              <input type="number" class="form-control" id="order_position" name="order_position" value="0" min="0">
            </div>

            {{-- Estado Activo --}}
            <div class="col-md-3 mb-3">
              <label class="form-label d-block">Estado</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                <label class="form-check-label" for="is_active">Activo</label>
              </div>
            </div>
          </div>

          {{-- Visibilidad por Scope --}}
          <div class="row">
            <div class="col-md-12 mb-3">
              <label class="form-label d-block">Visibilidad</label>
              <div class="d-flex gap-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="show_in_superadmin" name="show_in_superadmin" checked>
                  <label class="form-check-label" for="show_in_superadmin">
                    <i class="bi bi-shield-lock me-1"></i> Mostrar en Superadmin
                    <small class="text-muted d-block">Se muestra en el panel /musedock/</small>
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="show_in_tenant" name="show_in_tenant" checked>
                  <label class="form-check-label" for="show_in_tenant">
                    <i class="bi bi-building me-1"></i> Copiar a Tenants
                    <small class="text-muted d-block">Se copia a nuevos tenants al crearlos</small>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            {{-- Icono --}}
            <div class="col-md-4 mb-3">
              <label for="icon" class="form-label">Icono</label>
              <div class="input-group">
                <span class="input-group-text" id="icon-preview">
                  <i class="bi bi-app"></i>
                </span>
                <input type="text" class="form-control" id="icon" name="icon" placeholder="app">
                <button class="btn btn-outline-secondary" type="button" id="iconPickerBtn">
                  <i class="bi bi-search"></i> Buscar
                </button>
              </div>
              <small class="text-muted">Nombre del icono sin prefijo (ej: house, gear)</small>
            </div>

            {{-- Tipo de Icono --}}
            <div class="col-md-2 mb-3">
              <label for="icon_type" class="form-label">Tipo</label>
              <select class="form-select" id="icon_type" name="icon_type">
                <option value="bi" selected>Bootstrap Icons</option>
                <option value="fas">FA Solid</option>
                <option value="far">FA Regular</option>
                <option value="fal">FA Light</option>
              </select>
            </div>

            {{-- Menú Padre --}}
            <div class="col-md-3 mb-3">
              <label for="parent_id" class="form-label">Menú Padre</label>
              <select class="form-select" id="parent_id" name="parent_id">
                <option value="">Ninguno (Menú Principal)</option>
                @if(!empty($parentMenus))
                  @foreach($parentMenus as $parent)
                    <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                  @endforeach
                @endif
              </select>
            </div>

            {{-- Módulo --}}
            <div class="col-md-3 mb-3">
              <label for="module_id" class="form-label">Módulo</label>
              <select class="form-select" id="module_id" name="module_id">
                <option value="">Ninguno (Sistema)</option>
                @if(!empty($modules))
                  @foreach($modules as $module)
                    <option value="{{ $module->id }}">{{ $module->name }}</option>
                  @endforeach
                @endif
              </select>
            </div>
          </div>

          <div class="row">
            {{-- Permiso --}}
            <div class="col-md-12 mb-3">
              <label for="permission" class="form-label">Permiso (opcional)</label>
              <input type="text" class="form-control" id="permission" name="permission" placeholder="admin.settings.view">
              <small class="text-muted">Permiso requerido para ver este menú</small>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin-menus.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear Menú</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

{{-- Modal Selector de Iconos --}}
<div class="modal fade" id="iconPickerModal" tabindex="-1" aria-labelledby="iconPickerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="iconPickerModalLabel">Selector de Iconos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control mb-3" id="iconSearch" placeholder="Buscar icono...">
        <div id="iconGrid" class="row g-2" style="max-height: 400px; overflow-y: auto;">
          <!-- Los iconos se cargarán aquí dinámicamente -->
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const titleInput = document.getElementById('title');
  const slugInput = document.getElementById('slug');
  const iconInput = document.getElementById('icon');
  const iconTypeSelect = document.getElementById('icon_type');
  const iconPreview = document.getElementById('icon-preview');
  const iconPickerBtn = document.getElementById('iconPickerBtn');
  const iconPickerModal = new bootstrap.Modal(document.getElementById('iconPickerModal'));
  const iconGrid = document.getElementById('iconGrid');
  const iconSearch = document.getElementById('iconSearch');

  // Auto-generar slug desde el título
  titleInput.addEventListener('input', function() {
    const slug = this.value
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
    slugInput.value = slug;
  });

  // Actualizar preview del icono
  function updateIconPreview() {
    const iconName = iconInput.value.trim();
    const iconType = iconTypeSelect.value;

    if (!iconName) {
      iconPreview.innerHTML = '<i class="bi bi-app"></i>';
      return;
    }

    if (iconType === 'bi') {
      iconPreview.innerHTML = `<i class="bi bi-${iconName}"></i>`;
    } else {
      iconPreview.innerHTML = `<i class="${iconType} fa-${iconName}"></i>`;
    }
  }

  iconInput.addEventListener('input', updateIconPreview);
  iconTypeSelect.addEventListener('change', updateIconPreview);

  // Iconos de Bootstrap Icons más comunes
  const bootstrapIcons = [
    'house', 'speedometer2', 'grid', 'file-text', 'folder', 'people', 'person',
    'gear', 'sliders', 'palette', 'image', 'camera', 'film', 'music-note',
    'headphones', 'mic', 'telephone', 'envelope', 'chat', 'bell', 'calendar',
    'clock', 'map', 'pin-map', 'globe', 'cloud', 'download', 'upload', 'share',
    'bookmark', 'heart', 'star', 'flag', 'tag', 'shield', 'lock', 'unlock',
    'key', 'search', 'zoom-in', 'zoom-out', 'eye', 'eye-slash', 'pencil',
    'trash', 'plus', 'dash', 'x', 'check', 'arrow-left', 'arrow-right',
    'arrow-up', 'arrow-down', 'chevron-left', 'chevron-right', 'list', 'grid-3x3',
    'layout-sidebar', 'layout-text-window', 'box', 'archive', 'clipboard',
    'file-earmark', 'newspaper', 'journal', 'book', 'shop', 'cart', 'bag',
    'credit-card', 'wallet', 'piggy-bank', 'graph-up', 'graph-down', 'pie-chart',
    'bar-chart', 'table', 'kanban', 'diagram-3', 'bezier', 'building', 'building-gear',
    'hospital', 'hospital-fill', 'airplane', 'train-front', 'truck', 'bicycle',
    'cup', 'cup-hot', 'trophy', 'gift', 'award', 'puzzle', 'collection',
    'database', 'server', 'hdd', 'usb', 'plug', 'lightning', 'brightness-high',
    'moon', 'sun', 'cloud-sun', 'cloud-rain', 'snow', 'wind', 'thermometer',
    'droplet', 'fire', 'tree', 'flower', 'bug', 'joystick', 'controller',
    'cpu', 'memory', 'gpu-card', 'webcam', 'tv', 'laptop', 'phone', 'tablet',
    'smartwatch', 'printer', 'keyboard', 'mouse', 'bluetooth', 'wifi', 'broadcast',
    'reception', 'badge', 'patch-check', 'megaphone', 'bullhorn', 'briefcase'
  ];

  // Abrir modal de selección de iconos
  iconPickerBtn.addEventListener('click', function() {
    renderIcons(bootstrapIcons);
    iconPickerModal.show();
  });

  // Renderizar iconos en el grid
  function renderIcons(icons) {
    iconGrid.innerHTML = '';
    const iconType = iconTypeSelect.value;

    icons.forEach(iconName => {
      const iconDiv = document.createElement('div');
      iconDiv.className = 'col-2 text-center';
      iconDiv.innerHTML = `
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 icon-select-btn" data-icon="${iconName}">
          <i class="${iconType === 'bi' ? 'bi bi-' + iconName : iconType + ' fa-' + iconName}" style="font-size: 1.5rem;"></i>
          <br>
          <small>${iconName}</small>
        </button>
      `;
      iconGrid.appendChild(iconDiv);
    });

    // Event listener para seleccionar iconos
    iconGrid.querySelectorAll('.icon-select-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const selectedIcon = this.getAttribute('data-icon');
        iconInput.value = selectedIcon;
        updateIconPreview();
        iconPickerModal.hide();
      });
    });
  }

  // Búsqueda de iconos
  iconSearch.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const filteredIcons = bootstrapIcons.filter(icon => icon.includes(searchTerm));
    renderIcons(filteredIcons);
  });
});
</script>
@endpush
