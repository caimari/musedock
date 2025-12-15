@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $title }}</h2>
            <div>
                <a href="/musedock/blog/posts/{{ $post->id }}/revisions" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver a revisiones
                </a>
                <form method="POST" action="/musedock/blog/posts/{{ $post->id }}/revisions/{{ $revision->id }}/restore" style="display:inline;" class="restore-revision-form">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar esta versión
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Revisión ID:</strong> #{{ $revision->id }}</p>
                        <p><strong>Creado:</strong> {{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}</p>
                        <p><strong>Tipo:</strong> <span class="badge bg-info">{{ $revision->revision_type }}</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Usuario:</strong> {{ e($revision->user_name ?? 'Sistema') }}</p>
                        <p><strong>Cambios:</strong> {{ e($revision->changes_summary ?? 'Sin descripción') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @if($revision->featured_image)
                <div class="mb-3">
                    <img src="/{{ $revision->featured_image }}" class="img-fluid rounded" style="max-width:100%; max-height:400px; object-fit:cover;" alt="Imagen destacada">
                </div>
                @endif

                <h3 class="mb-3">{{ e($revision->title) }}</h3>

                @if($revision->excerpt)
                <div class="alert alert-info mb-3">
                    <strong>Extracto:</strong> {{ e($revision->excerpt) }}
                </div>
                @endif

                <hr>

                <div class="revision-content">
                    {!! $revision->content !!}
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('.restore-revision-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    Swal.fire({
      icon: 'warning',
      title: {!! json_encode(__('blog.post.confirm_restore_revision_title')) !!},
      text: {!! json_encode(__('blog.post.confirm_restore_revision_text')) !!},
      showCancelButton: true,
      confirmButtonText: {!! json_encode(__('blog.post.confirm_restore_revision_yes')) !!},
      cancelButtonText: {!! json_encode(__('common.cancel')) !!},
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#6c757d'
    }).then(function (result) {
      if (result.isConfirmed) {
        form.submit();
      }
    });
  });
});
</script>
@endpush
@endsection
