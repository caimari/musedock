@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content"><div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-3"><h2>{{ $title }}</h2>
<div><a href="{{ route('tenant.pages.revisions', $page->id) }}" class="btn btn-secondary me-2"><i class="bi bi-arrow-left"></i> Volver a revisiones</a>
<button type="button" class="btn btn-primary btn-restore" data-revision-date="{{ date('d/m/Y H:i', strtotime($revision->created_at)) }}" data-restore-url="{{ route('tenant.pages.revisions.restore', [$page->id, $revision->id]) }}"><i class="bi bi-arrow-counterclockwise"></i> Restaurar esta versión</button></div>
</div>
<div class="card"><div class="card-body"><h5>{{ e($revision->title) }}</h5><hr><div>{!! nl2br(e($revision->content)) !!}</div></div></div>
</div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.btn-restore').addEventListener('click', function() {
        const revisionDate = this.dataset.revisionDate;
        const restoreUrl = this.dataset.restoreUrl;

        Swal.fire({
            title: '¿Restaurar versión?',
            html: `<p>La página volverá al estado del <strong>${revisionDate}</strong>.</p><p class="text-muted"><small>Se creará una nueva revisión con el estado actual antes de restaurar.</small></p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar',
            cancelButtonText: 'Cancelar',
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = restoreUrl;
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
});
</script>
@endsection
