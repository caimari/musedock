@extends('layouts::app')

@section('title', $title ?? __forms('form.create'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('tenant.custom-forms.index') }}">{{ __forms('form.forms') }}</a></li>
                    <li class="breadcrumb-item active">{{ __forms('form.create') }}</li>
                </ol>
            </nav>
            <h2 class="mb-0"><i class="bi bi-plus-circle me-2"></i>{{ $title ?? __forms('form.create') }}</h2>
        </div>

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>{!! session('error') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('tenant.custom-forms.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.basic_info') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __forms('form.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required placeholder="{{ __forms('form.name_placeholder') }}">
                            </div>
                            <div class="mb-3">
                                <label for="slug" class="form-label">{{ __forms('form.slug') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug') }}" pattern="[a-z0-9\-]+">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __forms('form.description') }}</label>
                                <textarea class="form-control" id="description" name="description" rows="2">{{ old('description') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="submit_button_text" class="form-label">{{ __forms('form.submit_button_text') }}</label>
                                <input type="text" class="form-control" id="submit_button_text" name="submit_button_text" value="{{ old('submit_button_text', 'Enviar') }}">
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.messages') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="success_message" class="form-label">{{ __forms('form.success_message') }}</label>
                                <textarea class="form-control" id="success_message" name="success_message" rows="2">{{ old('success_message', $settings['default_success_message'] ?? '') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="error_message" class="form-label">{{ __forms('form.error_message') }}</label>
                                <textarea class="form-control" id="error_message" name="error_message" rows="2">{{ old('error_message', $settings['default_error_message'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0"><i class="bi bi-envelope me-2"></i>{{ __forms('form.email_settings') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="email_to" class="form-label">{{ __forms('form.email_to') }}</label>
                                <input type="text" class="form-control" id="email_to" name="email_to" value="{{ old('email_to') }}" placeholder="admin@example.com">
                                <div class="form-text">{{ __forms('form.email_to_help') }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="email_subject" class="form-label">{{ __forms('form.email_subject') }}</label>
                                <input type="text" class="form-control" id="email_subject" name="email_subject" value="{{ old('email_subject') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.status') }}</h5></div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">{{ __forms('form.is_active') }}</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="store_submissions" id="store_submissions" value="1" checked>
                                <label class="form-check-label" for="store_submissions">{{ __forms('form.store_submissions') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-1"></i> {{ __forms('form.create') }}
                                </button>
                                <a href="{{ route('tenant.custom-forms.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i> {{ __forms('form.cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('name').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.dataset.manual) {
        slugInput.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }
});
document.getElementById('slug').addEventListener('input', function() {
    this.dataset.manual = this.value.length > 0 ? '1' : '';
});
</script>
@endpush
