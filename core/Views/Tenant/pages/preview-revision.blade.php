@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $title }}</h2>
            <div>
                <a href="{{ admin_url('pages') }}/{{ $page->id }}/revisions" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Volver a revisiones
                </a>
<button type="button" class="btn btn-primary btn-restore" data-revision-date="{{ date('d/m/Y H:i', strtotime($revision->created_at)) }}">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar esta versión
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="mb-3">{{ e($revision->title) }}</h3>
                <hr>
                <div class="revision-content">
                    {!! $revision->content !!}
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="text-muted">Metadatos de la revisión</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li><strong>Tipo:</strong> {{ ucfirst($revision->revision_type) }}</li>
                    <li><strong>Resumen:</strong> {{ $revision->summary }}</li>
                    <li><strong>Fecha:</strong> {{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}</li>
                    @if($revision->user_agent)
                    <li><strong>Navegador:</strong> {{ $revision->user_agent }}</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.btn-restore').addEventListener('click', function() {
        const revisionDate = this.dataset.revisionDate;

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
                form.action = '{{ admin_url('pages') }}/{{ $page->id }}/revisions/{{ $revision->id }}/restore';
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
