@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>{{ $title }}</h2>
<a href="{{ admin_url('blog') }}/posts" class="btn btn-outline-secondary"><i class="bi bi-list"></i> Volver a lista de posts</a>
</div>
@if (session('success'))<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'success',title:'Correcto',text:{!!json_encode(session('success'))!!},confirmButtonColor:'#3085d6'});});</script>@endif
@if (session('error'))<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'error',title:'Error',text:{!!json_encode(session('error'))!!},confirmButtonColor:'#d33'});});</script>@endif
<div class="alert alert-warning"><i class="bi bi-info-circle"></i> Los posts en la papelera se eliminarán permanentemente después de 30 días.</div>
<div class="card"><div class="card-body">
@if (empty($posts))
<p class="text-muted">No hay posts en la papelera.</p>
@else
<table class="table table-hover">
<thead><tr><th>Título</th><th>Eliminado</th><th>Por</th><th>Auto-eliminación</th><th style="width:200px;">Acciones</th></tr></thead>
<tbody>
@foreach ($posts as $post)
@php $info = $trashInfo[$post->id] ?? null; @endphp
<tr>
<td><strong>{{ e($post->title) }}</strong><br><small class="text-muted">{{ e($post->slug) }}</small></td>
<td>{{ $info ? date('d/m/Y H:i', strtotime($info['deleted_at'])) : '-' }}</td>
<td>{{ e($info['deleted_by_name'] ?? 'Desconocido') }}<br><small class="text-muted">{{ e($info['deleted_by_type'] ?? '') }}</small></td>
<td>@if($info && $info['scheduled_permanent_delete'])<span class="text-danger">{{ date('d/m/Y', strtotime($info['scheduled_permanent_delete'])) }}</span>@else-@endif</td>
<td>
<div class="d-inline-flex align-items-center gap-2">
<form method="POST" action="{{ admin_url('blog') }}/posts/{{ $post->id }}/restore" style="display:inline;" class="restore-post-form" data-post-title="{{ htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8') }}">
<input type="hidden" name="_token" value="{{ csrf_token() }}">
<button type="submit" class="btn btn-success" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
</form>
<form method="POST" action="{{ admin_url('blog') }}/posts/{{ $post->id }}/force-delete" style="display:inline;" class="force-delete-post-form" data-post-title="{{ htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8') }}">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('submit', function(e) {
    const form = e.target;
    if (form && form.classList && form.classList.contains('restore-post-form')) {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();

      Swal.fire({
        icon: 'warning',
        title: {!! json_encode(__('blog.post.confirm_restore_from_trash_title')) !!},
        text: {!! json_encode(__('blog.post.confirm_restore_from_trash_text')) !!},
        showCancelButton: true,
        confirmButtonText: {!! json_encode(__('blog.post.confirm_restore_from_trash_yes')) !!},
        cancelButtonText: {!! json_encode(__('common.cancel')) !!},
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d'
      }).then((result) => {
        if (result.isConfirmed) {
          form.dataset.confirmed = '1';
          form.submit();
        }
      });
      return;
    }
    if (!form || !form.classList || !form.classList.contains('force-delete-post-form')) return;

    if (form.dataset.confirmed === '1') return;
    e.preventDefault();

    const postTitle = form.getAttribute('data-post-title') || '';

    Swal.fire({
      title: {!! json_encode(__('common.are_you_sure')) !!},
      html: {!! json_encode(__('blog.delete.confirm_delete_post', ['title' => ':title'])) !!}.replace(':title', escapeHtml(postTitle)),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
      cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
    }).then((result) => {
      if (result.isConfirmed) {
        form.dataset.confirmed = '1';
        form.submit();
      }
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
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }
});
</script>
@endpush
