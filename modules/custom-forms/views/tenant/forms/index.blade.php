@extends('layouts::app')

@section('title', __forms('form.forms'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-ui-checks me-2"></i>{{ __forms('form.forms') }}</h2>
                <p class="text-muted mb-0">{{ __forms('form.manage_forms') }}</p>
            </div>
            <a href="{{ route('tenant.custom-forms.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.create') }}
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __forms('form.name') }}</th>
                                <th>{{ __forms('form.shortcode') }}</th>
                                <th class="text-center">{{ __forms('form.fields') }}</th>
                                <th class="text-center">{{ __forms('form.submissions') }}</th>
                                <th class="text-center">{{ __forms('form.status') }}</th>
                                <th style="width: 180px;">{{ __forms('form.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($forms ?? [] as $form)
                            <tr>
                                <td>
                                    <strong>{{ $form->name }}</strong>
                                    @if($form->description)
                                        <br><small class="text-muted">{{ Str::limit($form->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="max-width: 200px;">
                                        <input type="text" class="form-control font-monospace" value="[custom-form id={{ $form->id }}]" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('[custom-form id={{ $form->id }}]')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $form->field_count ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info">{{ $form->submission_count ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    @if($form->is_active)
                                        <span class="badge bg-success">{{ __forms('form.active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __forms('form.inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('tenant.custom-forms.edit', $form->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('tenant.custom-forms.submissions', $form->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-inbox"></i>
                                        </a>
                                        <form action="{{ route('tenant.custom-forms.destroy', $form->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __forms('form.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-ui-checks display-1 text-muted"></i>
                                    <h4 class="mt-3">{{ __forms('form.no_forms') }}</h4>
                                    <p class="text-muted">{{ __forms('form.no_forms_desc') }}</p>
                                    <a href="{{ route('tenant.custom-forms.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.create_first') }}
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('{{ __forms("form.shortcode_copied") }}');
    });
}
</script>
@endpush
