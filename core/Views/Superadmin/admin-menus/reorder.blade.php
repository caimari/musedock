@extends('layouts.app')

@section('title', $title ?? 'Ordenar Menús del Administrador')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botones --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? 'Ordenar Menús del Administrador' }}</h2>
      <div>
        <a href="{{ route('admin-menus.index') }}" class="btn btn-secondary me-2">
          <i class="bi bi-arrow-left"></i> Volver
        </a>
        <button type="button" class="btn btn-success" id="saveOrderBtn">
          <i class="bi bi-check-circle"></i> Guardar Orden
        </button>
      </div>
    </div>

    {{-- Alertas --}}
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i>
      <strong>Instrucciones:</strong> Arrastra y suelta los elementos para cambiar su orden. También puedes anidar elementos como submenús arrastrándolos dentro de otros elementos.
    </div>

    {{-- Lista de Menús --}}
    <div class="card">
      <div class="card-body">
        <div id="menuList" class="menu-sortable-list">
          @if(!empty($menus))
            @foreach($menus as $menu)
              @include('admin-menus.partials.menu-item', ['menu' => $menu, 'level' => 0])
            @endforeach
          @else
            <p class="text-muted text-center">No hay menús para ordenar.</p>
          @endif
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>
.menu-sortable-list {
  list-style: none;
  padding: 0;
}

.menu-item {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  padding: 1rem;
  margin-bottom: 0.5rem;
  cursor: move;
  transition: all 0.2s;
}

.menu-item:hover {
  background: #e9ecef;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.menu-item.dragging {
  opacity: 0.5;
}

.menu-item.drag-over {
  border-color: #0d6efd;
  border-style: dashed;
}

.menu-item-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.menu-item-icon {
  font-size: 1.5rem;
  color: #6c757d;
}

.menu-item-info {
  flex: 1;
}

.menu-item-title {
  font-weight: 600;
  margin: 0;
}

.menu-item-meta {
  font-size: 0.875rem;
  color: #6c757d;
  margin: 0;
}

.menu-item-children {
  list-style: none;
  padding-left: 2rem;
  margin-top: 0.5rem;
}

.menu-item-children .menu-item {
  background: #ffffff;
}

.menu-item-drag-handle {
  cursor: grab;
  color: #6c757d;
  padding: 0.5rem;
}

.menu-item-drag-handle:active {
  cursor: grabbing;
}

.badge-parent {
  background-color: #0d6efd;
}

.badge-child {
  background-color: #6c757d;
}

.badge-inactive {
  background-color: #dc3545;
}
</style>
@endpush

@push('scripts')
<!-- Sortable.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Inicializar Sortable en la lista principal
  const menuList = document.getElementById('menuList');

  if (!menuList) {
    console.error('No se encontró el contenedor de menús');
    return;
  }

  // Configuración de Sortable
  const sortableOptions = {
    group: 'nested',
    animation: 150,
    fallbackOnBody: true,
    swapThreshold: 0.65,
    handle: '.menu-item-drag-handle',
    draggable: '.menu-item',
    ghostClass: 'dragging',
    chosenClass: 'drag-over',
    onEnd: function(evt) {
      console.log('Item movido', evt);
    }
  };

  // Aplicar Sortable a la lista principal
  new Sortable(menuList, sortableOptions);

  // Aplicar Sortable a todas las sublistas
  document.querySelectorAll('.menu-item-children').forEach(function(el) {
    new Sortable(el, sortableOptions);
  });

  // Guardar orden
  const saveOrderBtn = document.getElementById('saveOrderBtn');

  saveOrderBtn.addEventListener('click', function() {
    const menuOrder = serializeMenuOrder(menuList);

    console.log('Orden de menús:', menuOrder);

    // Enviar al servidor
    fetch('{{ route("admin-menus.update-order") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'csrf_token={{ csrf_token() }}&menu_order=' + encodeURIComponent(JSON.stringify(menuOrder))
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Orden guardado',
          text: 'El orden de los menús se ha actualizado correctamente',
          confirmButtonColor: '#3085d6'
        }).then(() => {
          window.location.href = '{{ route("admin-menus.index") }}';
        });
      } else {
        throw new Error(data.error || 'Error desconocido');
      }
    })
    .catch(error => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error al guardar el orden: ' + error.message,
        confirmButtonColor: '#d33'
      });
    });
  });

  // Serializar el orden de los menús
  function serializeMenuOrder(container) {
    const items = [];
    const children = container.children;

    for (let i = 0; i < children.length; i++) {
      const item = children[i];
      const id = item.getAttribute('data-id');
      const childrenContainer = item.querySelector('.menu-item-children');

      const menuItem = {
        id: id,
        children: childrenContainer ? serializeMenuOrder(childrenContainer) : []
      };

      items.push(menuItem);
    }

    return items;
  }
});
</script>
@endpush
