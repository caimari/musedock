@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h2 class="mb-4">{{ $title }}</h2>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <a href="/{{ admin_path() }}/languages/create" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i> Añadir nuevo idioma
      </a>
      <small class="text-muted">
        <i class="bi bi-arrows-move me-1"></i> Arrastra las filas para cambiar el orden
      </small>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0" id="languages-table">
          <thead>
            <tr>
              <th style="width: 40px;"></th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="sortable-languages">
            @foreach ($languages as $lang)
            <tr data-id="{{ $lang->id }}">
              <td class="drag-handle" style="cursor: grab;">
                <i class="bi bi-grip-vertical text-muted"></i>
              </td>
              <td><code>{{ $lang->code }}</code></td>
              <td>{{ $lang->name }}</td>
              <td>
                <form method="POST" action="/{{ admin_path() }}/languages/{{ $lang->id }}/toggle" class="d-inline toggle-form">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm {{ $lang->active ? 'btn-success' : 'btn-outline-secondary' }}">
                    <i class="bi {{ $lang->active ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>
                    {{ $lang->active ? 'Activo' : 'Inactivo' }}
                  </button>
                </form>
              </td>
              <td>
                <a href="/{{ admin_path() }}/languages/{{ $lang->id }}/edit" class="btn btn-sm btn-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <button type="button" class="btn btn-sm btn-danger btn-delete-lang"
                        data-id="{{ $lang->id }}"
                        data-name="{{ $lang->name }}"
                        data-code="{{ $lang->code }}">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Configuración de idioma predeterminado --}}
    @php
      $activeLanguages = array_filter($languages, function($lang) {
          return $lang->active == 1;
      });
    @endphp
    <div class="card mt-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-globe me-2"></i>Idioma predeterminado</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="/{{ admin_path() }}/languages/set-default" id="default-lang-form">
          {!! csrf_field() !!}
          <div class="row align-items-end">
            <div class="col-md-6">
              <label class="form-label">Idioma predeterminado del sitio</label>
              <select name="default_lang" class="form-select" id="default-lang-select">
                @foreach($activeLanguages as $lang)
                  <option value="{{ $lang->code }}" {{ $default_lang === $lang->code ? 'selected' : '' }}>
                    {{ $lang->name }} ({{ $lang->code }})
                  </option>
                @endforeach
              </select>
              <small class="form-text text-muted">
                Este es el idioma que se mostrará por defecto en tu sitio web.
              </small>
            </div>
            <div class="col-md-6">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Guardar idioma predeterminado
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header" style="background-color: #e7f1ff; color: #0d6efd;">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h5>
      </div>
      <div class="card-body">
        <ul class="mb-0">
          <li><strong>Idiomas de fábrica:</strong> Español e Inglés vienen activados por defecto.</li>
          <li><strong>Añadir más idiomas:</strong> Puedes añadir todos los idiomas que necesites (francés, alemán, portugués, etc.).</li>
          <li><strong>Orden de idiomas:</strong> El orden define cómo aparecen en los selectores del front-end.</li>
          <li><strong>Idiomas activos:</strong> Solo los idiomas activos se muestran en los selectores. Debe haber al menos un idioma activo.</li>
          <li><strong>Idioma único:</strong> Si solo hay un idioma activo, los selectores de idioma se ocultan automáticamente.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .sortable-ghost {
    opacity: 0.4;
    background: #c8ebfb !important;
  }
  .sortable-chosen {
    background: #f8f9fa !important;
  }
  .drag-handle:hover {
    cursor: grabbing;
  }
  #sortable-languages tr {
    transition: background-color 0.2s;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tbody = document.getElementById('sortable-languages');

  // Drag & Drop ordering
  if (tbody && typeof Sortable !== 'undefined') {
    new Sortable(tbody, {
      handle: '.drag-handle',
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: function() {
        const rows = tbody.querySelectorAll('tr[data-id]');
        const order = Array.from(rows).map(row => row.dataset.id);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch('/{{ admin_path() }}/languages/update-order', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({ order: order })
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Error HTTP: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
              <div class="toast show" role="alert">
                <div class="toast-body bg-success text-white rounded">
                  <i class="bi bi-check-circle me-1"></i> Orden actualizado
                </div>
              </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
          } else {
            console.error('Error en respuesta:', data);
            alert('Error al guardar el orden: ' + (data.error || 'Error desconocido'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error al guardar el orden. Comprueba la consola para más detalles.');
        });
      }
    });
  }

  // Delete with SweetAlert2 password confirmation
  document.querySelectorAll('.btn-delete-lang').forEach(btn => {
    btn.addEventListener('click', function() {
      const langId = this.dataset.id;
      const langName = this.dataset.name;
      const langCode = this.dataset.code;

      Swal.fire({
        title: '¿Eliminar idioma?',
        html: `
          <p>Vas a eliminar el idioma <strong>${langName} (${langCode})</strong>.</p>
          <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
          <p>Escribe tu contraseña para confirmar:</p>
        `,
        input: 'password',
        inputPlaceholder: 'Tu contraseña',
        inputAttributes: {
          autocomplete: 'current-password'
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: (password) => {
          if (!password) {
            Swal.showValidationMessage('Debes introducir tu contraseña');
            return false;
          }

          // Create form and submit
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '/{{ admin_path() }}/languages/' + langId + '/delete';

          const csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = '_token';
          csrfInput.value = '{{ csrf_token() }}';
          form.appendChild(csrfInput);

          const passwordInput = document.createElement('input');
          passwordInput.type = 'hidden';
          passwordInput.name = 'password';
          passwordInput.value = password;
          form.appendChild(passwordInput);

          document.body.appendChild(form);
          form.submit();

          return true;
        },
        allowOutsideClick: () => !Swal.isLoading()
      });
    });
  });
});

// Mostrar mensajes flash con SweetAlert2
@if(session('success'))
  Swal.fire({
    icon: 'success',
    title: '¡Hecho!',
    text: '{{ session('success') }}',
    timer: 3000,
    showConfirmButton: false,
    toast: false
  });
@endif

@if(session('error'))
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '{{ session('error') }}',
    confirmButtonColor: '#dc3545'
  });
@endif

@if(session('warning'))
  Swal.fire({
    icon: 'warning',
    title: 'Atención',
    text: '{{ session('warning') }}',
    confirmButtonColor: '#ffc107'
  });
@endif
</script>
@endpush
