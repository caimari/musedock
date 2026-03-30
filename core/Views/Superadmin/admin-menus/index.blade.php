@extends('layouts.app')

@section('title', $title ?? 'Gestión de Menús del Administrador')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botones --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? 'Gestión de Menús del Administrador' }}</h2>
      <div>
        <a href="{{ route('admin-menus.reorder') }}" class="btn btn-secondary me-2">
          <i class="bi bi-arrow-down-up"></i> Ordenar Menús
        </a>
        <a href="{{ route('admin-menus.create') }}" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> Añadir Menú
        </a>
      </div>
    </div>

    {{-- Alertas con SweetAlert2 --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: 'Correcto',
            text: <?php echo json_encode(session('success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#3085d6'
          });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: <?php echo json_encode(session('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    {{-- Tabla de Menús --}}
    <div class="card">
      <div class="card-body table-responsive p-0">
        @if (!empty($menus) && count($menus) > 0)
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 1%;">Estado</th>
              <th>Título</th>
              <th>Slug</th>
              <th>URL</th>
              <th>Icono</th>
              <th>Módulo</th>
              <th>Orden</th>
              <th style="width: 15%;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @foreach ($menus as $menu)
            @include('admin-menus.partials.menu-row', ['menu' => $menu, 'level' => 0])
          @endforeach
          </tbody>
        </table>
        @else
          <div class="p-3 text-center">
            <p class="text-muted">No se encontraron menús.</p>
            <a href="{{ route('admin-menus.create') }}" class="btn btn-sm btn-primary">Crear el primer menú</a>
          </div>
        @endif
      </div>
    </div>

    {{-- Formularios de eliminación (generados dinámicamente por JS) --}}
    <div id="delete-forms-container"></div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Manejar eliminación de menús
  document.addEventListener('click', function(e) {
    const deleteBtn = e.target.closest('.delete-menu-btn');
    if (!deleteBtn) return;

    e.preventDefault();

    const menuId = deleteBtn.getAttribute('data-id');
    const menuTitle = deleteBtn.getAttribute('data-title');

    Swal.fire({
      title: '¿Estás seguro?',
      html: `Esta acción eliminará permanentemente el menú <strong>"${escapeHtml(menuTitle)}"</strong> y no se puede deshacer.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        // Crear formulario dinámicamente
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ route('admin-menus.index') }}/${menuId}/destroy`;

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
      }
    });
  });

  // Manejar toggle de estado activo
  document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', function() {
      const menuId = this.getAttribute('data-id');
      const isActive = this.checked;

      fetch(`{{ route('admin-menus.index') }}/toggle-active/${menuId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Estado actualizado',
            text: `El menú ahora está ${data.is_active ? 'activo' : 'inactivo'}`,
            timer: 2000,
            showConfirmButton: false
          });
        } else {
          throw new Error(data.error || 'Error desconocido');
        }
      })
      .catch(error => {
        this.checked = !isActive; // Revertir el toggle
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message
        });
      });
    });
  });

  // Función auxiliar para escapar HTML
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }
});
</script>
@endpush
