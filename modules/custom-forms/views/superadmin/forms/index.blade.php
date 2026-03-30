@extends('layouts::app')

@section('title', $title ?? __forms('form.forms'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-ui-checks-grid me-2"></i>{{ $title ?? __forms('form.forms') }}</h2>
                <p class="text-muted mb-0">{{ __forms('form.manage_forms') }}</p>
            </div>
            <a href="{{ route('custom-forms.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.create') }}
            </a>
        </div>

        <div class="alert alert-info d-flex align-items-start mb-4">
            <i class="bi bi-info-circle-fill me-3 mt-1" style="font-size: 1.2rem;"></i>
            <div>
                <h5 class="alert-heading mb-2">{{ __forms('form.legal_notice_title') }}</h5>
                <p class="mb-2">{{ __forms('form.legal_notice_intro') }}</p>
                <p class="mb-0">{{ __forms('form.legal_notice_info') }}</p>
            </div>
        </div>

        @if(!empty($forms))
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __forms('form.name') }}</th>
                                <th class="text-center">{{ __forms('form.fields') }}</th>
                                <th class="text-center">{{ __forms('form.submissions') }}</th>
                                <th class="text-center">{{ __forms('form.status') }}</th>
                                <th>{{ __forms('form.shortcode') }}</th>
                                <th class="text-end">{{ __forms('form.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($forms as $form)
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $form->name }}</div>
                                        <small class="text-muted">{{ $form->slug }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark">{{ $form->fieldCount() }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('custom-forms.submissions.list', ['formId' => $form->id]) }}" class="text-decoration-none">
                                            <span class="badge bg-primary">{{ $form->submissions_count ?? 0 }}</span>
                                            @if($form->unreadCount() > 0)
                                                <span class="badge bg-danger">{{ $form->unreadCount() }} {{ __forms('submission.new') }}</span>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $form->is_active ? 'success' : 'secondary' }}">
                                            {{ $form->is_active ? __forms('form.active') : __forms('form.inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        <code class="small user-select-all">[custom-form id={{ $form->id }}]</code>
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1" onclick="copyShortcode({{ $form->id }})" title="{{ __forms('form.copy') }}">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('custom-forms.edit', ['id' => $form->id]) }}" class="btn btn-outline-primary" title="{{ __forms('form.edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ route('custom-forms.submissions.list', ['formId' => $form->id]) }}" class="btn btn-outline-info" title="{{ __forms('submission.view') }}">
                                                <i class="bi bi-inbox"></i>
                                            </a>
                                            <a href="{{ route('custom-forms.duplicate', ['id' => $form->id]) }}" class="btn btn-outline-secondary" title="{{ __forms('form.duplicate') }}">
                                                <i class="bi bi-copy"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete({{ $form->id }}, '{{ e($form->name) }}')" title="{{ __forms('form.delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-ui-checks-grid display-1 text-muted mb-3"></i>
                    <h4>{{ __forms('form.no_forms') }}</h4>
                    <p class="text-muted mb-4">{{ __forms('form.no_forms_desc') }}</p>
                    <a href="{{ route('custom-forms.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.create_first') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>{{ __forms('form.usage_info') }}</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">{{ __forms('form.shortcode_info') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[custom-form id=1]</code>
                            <small class="d-block text-muted mt-1">{{ __forms('form.shortcode_by_id') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[custom-form slug="contacto"]</code>
                            <small class="d-block text-muted mt-1">{{ __forms('form.shortcode_by_slug') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: '{{ __forms("form.confirm_delete") }}',
        html: `<p>{{ __forms("form.delete_warning") }}</p><p class="fw-bold">${name}</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i>{{ __forms("form.delete") }}',
        cancelButtonText: '{{ __forms("form.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("custom-forms.index") }}/' + id + '/delete';
            form.innerHTML = '@csrf';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function copyShortcode(id) {
    const shortcode = '[custom-form id=' + id + ']';
    navigator.clipboard.writeText(shortcode).then(function() {
        Swal.fire({
            icon: 'success',
            title: '{{ __forms("form.copied") }}',
            text: shortcode,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'bottom-end'
        });
    });
}

// Show flash messages with SweetAlert2
@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Éxito',
    text: '{{ session("success") }}',
    confirmButtonText: 'OK'
});
@endif

@if(session('error'))
Swal.fire({
    icon: 'error',
    title: 'Error',
    html: '{!! addslashes(session("error")) !!}',
    confirmButtonText: 'OK'
});
@endif
</script>
@endpush
