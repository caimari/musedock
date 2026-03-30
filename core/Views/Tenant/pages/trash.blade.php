@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>{{ $title }}</h2>
<a href="{{ admin_url('pages') }}" class="btn btn-outline-secondary"><i class="bi bi-list"></i> Volver a lista de páginas</a>
</div>
@php $flashSuccess = consume_flash('success'); @endphp
@if ($flashSuccess)
<script>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    icon: 'success',
    title: {!! json_encode($flashSuccess) !!},
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });
});
</script>
@endif
<div class="alert alert-warning"><i class="bi bi-info-circle"></i> Las páginas en la papelera se eliminarán permanentemente después de 30 días.</div>
<div class="card"><div class="card-body">
@if (empty($pages))
<p class="text-muted">No hay páginas en la papelera.</p>
@else
<table class="table table-hover">
<thead><tr><th>Título</th><th>Eliminado</th><th>Por</th><th>Auto-eliminación</th><th style="width:200px;">Acciones</th></tr></thead>
<tbody>
@foreach ($pages as $page)
@php $info = $trashInfo[$page->id] ?? null; @endphp
<tr>
<td><strong>{{ e($page->title) }}</strong><br><small class="text-muted">{{ e($page->slug) }}</small></td>
<td>{{ $info ? date('d/m/Y H:i', strtotime($info['deleted_at'])) : '-' }}</td>
<td>{{ e($info['deleted_by_name'] ?? 'Desconocido') }}<br><small class="text-muted">{{ e($info['deleted_by_type'] ?? '') }}</small></td>
<td>@if($info && $info['scheduled_permanent_delete'])<span class="text-danger">{{ date('d/m/Y', strtotime($info['scheduled_permanent_delete'])) }}</span>@else-@endif</td>
<td>
<div class="btn-group btn-group-sm">
<form method="POST" action="{{ admin_url('pages') }}/{{ $page->id }}/restore" style="display:inline;">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<button type="submit" class="btn btn-success" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
</form>
<form method="POST" action="{{ admin_url('pages') }}/{{ $page->id }}/force-delete" style="display:inline;" onsubmit="return confirm('¿ELIMINAR PERMANENTEMENTE esta página?');">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<input type="hidden" name="_method" value="DELETE">
<button type="submit" class="btn btn-danger" title="Eliminar permanentemente"><i class="bi bi-trash"></i></button>
</form>
</div>
</td>
</tr>
@endforeach
</tbody>
</table>
@endif
</div></div>
</div></div>
@endsection
