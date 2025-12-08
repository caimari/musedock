@extends('layouts.app')

@section('title', $title ?? 'Gestión de Menús')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? 'Gestión de Menús del Panel' }}</h2>
    </div>

    {{-- Descripción --}}
    <div class="alert alert-info mb-4">
      <i class="bi bi-info-circle me-2"></i>
      <strong>Gestiona los menús de tu panel de administración.</strong>
      Puedes editar, activar/desactivar y organizar los elementos del menú.
    </div>

    {{-- Alertas --}}
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
            @include('tenant-menus.partials.menu-row', ['menu' => $menu, 'level' => 0])
          @endforeach
          </tbody>
        </table>
        @else
          <div class="p-3 text-center">
            <p class="text-muted">No se encontraron menús.</p>
          </div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Manejar toggle de estado activo
  document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', function() {
      const menuId = this.getAttribute('data-id');
      const isActive = this.checked;

      fetch(`{{ route('tenant-menus.index') }}/toggle-active/${menuId}`, {
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
          this.checked = !this.checked;
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.error || 'No se pudo actualizar el estado',
            confirmButtonColor: '#d33'
          });
        }
      })
      .catch(error => {
        this.checked = !this.checked;
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Error de conexión',
          confirmButtonColor: '#d33'
        });
      });
    });
  });

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
