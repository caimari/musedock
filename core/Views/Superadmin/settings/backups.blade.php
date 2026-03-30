@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0"><i class="bi bi-database-down me-2"></i>{{ $title }}</h3>
    <button type="button" class="btn btn-primary" id="btn-create-backup">
      <i class="bi bi-plus-circle me-1"></i> Crear backup ahora
    </button>
  </div>
  <div class="card-body">

    {{-- Info --}}
    <div class="alert alert-info mb-4">
      <i class="bi bi-info-circle me-2"></i>
      <strong>Backup automático:</strong> Se ejecuta diariamente. Los backups se rotan cada <strong>{{ $retention_days }} días</strong>.
      Los archivos se guardan en <code>storage/backups/db/</code>.
    </div>

    {{-- Config --}}
    <form method="POST" action="/musedock/settings/backups">
      {!! csrf_field() !!}
      <div class="row mb-4">
        <div class="col-md-4">
          <label class="form-label">Retención (días)</label>
          <input type="number" class="form-control" name="backup_retention_days" value="{{ $retention_days }}" min="1" max="365">
          <small class="text-muted">Los backups más antiguos se eliminan automáticamente.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">Backup automático</label>
          <select class="form-select" name="backup_auto_enabled">
            <option value="1" @selected($auto_enabled)>Activado</option>
            <option value="0" @selected(!$auto_enabled)>Desactivado</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary">
            <i class="bi bi-check-lg me-1"></i> Guardar configuración
          </button>
        </div>
      </div>
    </form>

    <hr>

    {{-- Backup list --}}
    <h5 class="mb-3"><i class="bi bi-archive me-2"></i>Backups disponibles ({{ count($backups) }})</h5>

    @if(empty($backups))
      <div class="alert alert-secondary">
        <i class="bi bi-inbox me-2"></i> No hay backups disponibles. Crea uno manualmente o espera al backup automático diario.
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th>Archivo</th>
              <th>Fecha</th>
              <th>Tamaño</th>
              <th>Tipo</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($backups as $backup)
            <tr>
              <td>
                <i class="bi bi-file-earmark-zip me-1 text-warning"></i>
                <code class="small">{{ $backup['filename'] }}</code>
              </td>
              <td>{{ $backup['date'] }}</td>
              <td>{{ $backup['size'] }}</td>
              <td>
                @if($backup['is_auto'])
                  <span class="badge bg-info">Auto</span>
                @else
                  <span class="badge bg-primary">Manual</span>
                @endif
              </td>
              <td class="text-end">
                <a href="/musedock/settings/backups/download?file={{ urlencode($backup['filename']) }}"
                   class="btn btn-sm btn-outline-success" title="Descargar">
                  <i class="bi bi-download"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-warning btn-restore"
                        data-file="{{ $backup['filename'] }}" title="Restaurar">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-backup"
                        data-file="{{ $backup['filename'] }}" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('[name="_csrf"]')?.value || '{{ csrf_token() }}';

    // Create backup
    document.getElementById('btn-create-backup')?.addEventListener('click', async function() {
        const confirm = await Swal.fire({
            title: 'Crear backup',
            text: 'Se creará una copia de seguridad de la base de datos.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Crear backup',
            cancelButtonText: 'Cancelar'
        });
        if (!confirm.isConfirmed) return;

        Swal.fire({ title: 'Creando backup...', html: 'Esto puede tardar unos segundos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch('/musedock/settings/backups/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ _csrf: csrf })
            });
            const text = await res.text();
            console.log('Backup response status:', res.status, 'body:', text.substring(0, 500));
            let data;
            try { data = JSON.parse(text); } catch(parseErr) {
                throw new Error('Respuesta inválida del servidor (status ' + res.status + '): ' + text.substring(0, 200));
            }
            if (!data.success) throw new Error(data.message);
            await Swal.fire({ icon: 'success', title: 'Backup creado', text: data.message || 'Backup completado.', timer: 2500, showConfirmButton: false });
            location.reload();
        } catch (e) {
            Swal.fire('Error', e.message || 'No se pudo crear el backup.', 'error');
        }
    });

    // Delete backup
    document.querySelectorAll('.btn-delete-backup').forEach(btn => {
        btn.addEventListener('click', async function() {
            const file = this.dataset.file;
            const result = await Swal.fire({
                title: 'Eliminar backup',
                html: `<p>¿Seguro que quieres eliminar este backup?</p><code>${file}</code>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!result.isConfirmed) return;

            try {
                const res = await fetch('/musedock/settings/backups/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ file: file, _csrf: csrf })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                await Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
                location.reload();
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        });
    });

    // Restore backup
    document.querySelectorAll('.btn-restore').forEach(btn => {
        btn.addEventListener('click', async function() {
            const file = this.dataset.file;

            const result = await Swal.fire({
                title: 'Restaurar backup',
                icon: 'warning',
                html: `
                    <div class="text-start">
                        <div class="alert alert-danger py-2 mb-3">
                            <strong>ATENCIÓN:</strong> Esto reemplazará TODA la base de datos actual.
                            Se creará un backup previo automáticamente.
                        </div>
                        <p class="mb-2">Archivo: <code>${file}</code></p>
                        <div class="mb-0">
                            <label class="form-label fw-bold small">Contraseña de administrador</label>
                            <input type="password" id="swal-restore-password" class="form-control" placeholder="Tu contraseña actual" autocomplete="off">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#e2a03f',
                focusConfirm: false,
                preConfirm: () => {
                    const password = document.getElementById('swal-restore-password').value;
                    if (!password) {
                        Swal.showValidationMessage('Debes introducir tu contraseña');
                        return false;
                    }
                    return password;
                }
            });

            if (!result.isConfirmed || !result.value) return;

            Swal.fire({ title: 'Restaurando...', html: 'Creando backup previo y restaurando la base de datos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            try {
                const res = await fetch('/musedock/settings/backups/restore', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ file: file, password: result.value, _csrf: csrf })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                await Swal.fire({ icon: 'success', title: 'Restaurado', text: data.message || 'Base de datos restaurada correctamente.', timer: 3000, showConfirmButton: false });
                location.reload();
            } catch (e) {
                Swal.fire('Error', e.message || 'No se pudo restaurar.', 'error');
            }
        });
    });
});
</script>
@endsection
