@extends('layouts.app')

@section('title', $title ?? __forms('form.edit'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
<link rel="stylesheet" href="/modules/custom-forms/css/form-builder.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('tenant.custom-forms.index') }}">{{ __forms('form.forms') }}</a></li>
                    <li class="breadcrumb-item active">{{ $form->name }}</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-pencil-square me-2"></i>{{ $form->name }}</h2>
                <a href="{{ route('tenant.custom-forms.submissions', $form->id) }}" class="btn btn-outline-info">
                    <i class="bi bi-inbox me-1"></i> {{ __forms('form.submissions') }} ({{ $form->submission_count ?? 0 }})
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card form-builder-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-ui-checks-grid me-2"></i>{{ __forms('form.form_builder') }}</h5>
                        <span class="badge bg-secondary" id="fieldCount">{{ count($fields ?? []) }} {{ __forms('form.fields') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="fieldList" class="field-list" data-form-id="{{ $form->id }}">
                            @forelse($fields ?? [] as $field)
                            <div class="field-item" data-field-id="{{ $field->id }}">
                                <div class="field-handle"><i class="bi bi-grip-vertical"></i></div>
                                <div class="field-info">
                                    <span class="field-type-badge badge bg-{{ $field->getTypeBadgeColor() }}">{{ $field->getTypeLabel() }}</span>
                                    <strong class="field-label">{{ $field->field_label }}</strong>
                                    <small class="field-name text-muted">{{ $field->field_name }}</small>
                                    @if($field->is_required)
                                        <span class="badge bg-danger">{{ __forms('form.required') }}</span>
                                    @endif
                                </div>
                                <div class="field-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editField({{ $field->id }})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteField({{ $field->id }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            @empty
                            <div class="empty-fields" id="emptyFieldsMsg">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="mt-3 mb-0">{{ __forms('form.no_fields') }}</p>
                                <small class="text-muted">{{ __forms('form.add_field_hint') }}</small>
                            </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                            <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.add_field') }}
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-eye me-2"></i>{{ __forms('form.preview') }}</h5></div>
                    <div class="card-body">
                        <div id="formPreview" class="form-preview"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <form action="{{ route('tenant.custom-forms.update', $form->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.settings') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __forms('form.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $form->name) }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __forms('form.description') }}</label>
                                <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $form->description) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="submit_button_text" class="form-label">{{ __forms('form.submit_button_text') }}</label>
                                <input type="text" class="form-control" id="submit_button_text" name="submit_button_text" value="{{ old('submit_button_text', $form->submit_button_text) }}">
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.messages') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="success_message" class="form-label">{{ __forms('form.success_message') }}</label>
                                <textarea class="form-control" id="success_message" name="success_message" rows="2">{{ old('success_message', $form->success_message) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="email_to" class="form-label">{{ __forms('form.email_to') }}</label>
                                <input type="text" class="form-control" id="email_to" name="email_to" value="{{ old('email_to', $form->email_to) }}">
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">{{ __forms('form.status') }}</h5></div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $form->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">{{ __forms('form.is_active') }}</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="store_submissions" id="store_submissions" value="1" {{ $form->store_submissions ? 'checked' : '' }}>
                                <label class="form-check-label" for="store_submissions">{{ __forms('form.store_submissions') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-1"></i> {{ __forms('form.save_changes') }}
                                </button>
                                <a href="{{ route('tenant.custom-forms.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> {{ __forms('form.back_to_list') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-code-square me-2"></i>{{ __forms('form.shortcode') }}</h6>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="shortcodeInput" value="[custom-form id={{ $form->id }}]" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyShortcode()"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>{{ __forms('field.add_field') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="fieldTypeSelector">
                    @foreach(\CustomForms\Models\Form::getFieldTypes() as $key => $type)
                    <div class="col-4 col-md-3">
                        <button type="button" class="field-type-btn" data-type="{{ $key }}" onclick="selectFieldType('{{ $key }}')">
                            <i class="bi bi-{{ $type['icon'] }}"></i>
                            <span>{{ $type['label'] }}</span>
                        </button>
                    </div>
                    @endforeach
                </div>
                <div id="fieldConfigForm" style="display: none;">
                    <hr>
                    <form id="addFieldForm">
                        <input type="hidden" name="field_type" id="newFieldType">
                        <input type="hidden" name="form_id" value="{{ $form->id }}">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __forms('field.label') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="field_label" id="newFieldLabel" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __forms('field.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="field_name" id="newFieldName" pattern="[a-z0-9_]+" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __forms('field.placeholder') }}</label>
                            <input type="text" class="form-control" name="placeholder" id="newFieldPlaceholder">
                        </div>
                        <div class="mb-3" id="optionsContainer" style="display: none;">
                            <label class="form-label">{{ __forms('field.options') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="options" id="newFieldOptions" rows="3" placeholder="{{ __forms('field.options_placeholder') }}"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_required" id="newFieldRequired" value="1">
                            <label class="form-check-label" for="newFieldRequired">{{ __forms('field.required') }}</label>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __forms('form.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="addFieldBtn" onclick="addField()" disabled>
                    <i class="bi bi-plus-lg me-1"></i> {{ __forms('field.add_field') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Field Modal -->
<div class="modal fade" id="editFieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>{{ __forms('field.edit_field') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editFieldForm">
                    <input type="hidden" name="field_id" id="editFieldId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __forms('field.type') }}</label>
                            <input type="text" class="form-control" id="editFieldTypeDisplay" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __forms('field.name') }}</label>
                            <input type="text" class="form-control" id="editFieldName" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __forms('field.label') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="field_label" id="editFieldLabel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __forms('field.placeholder') }}</label>
                        <input type="text" class="form-control" name="placeholder" id="editFieldPlaceholder">
                    </div>
                    <div class="mb-3" id="editOptionsContainer" style="display: none;">
                        <label class="form-label">{{ __forms('field.options') }}</label>
                        <textarea class="form-control" name="options" id="editFieldOptions" rows="3"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_required" id="editFieldRequired" value="1">
                        <label class="form-check-label" for="editFieldRequired">{{ __forms('field.required') }}</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __forms('form.cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="updateField()">
                    <i class="bi bi-check-lg me-1"></i> {{ __forms('field.save_changes') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="/modules/custom-forms/js/form-builder.js"></script>
<script>
const fieldTypes = @json(\CustomForms\Models\Form::getFieldTypes());
const formId = {{ $form->id }};
const isTenanView = true;

function copyShortcode() {
    document.getElementById('shortcodeInput').select();
    document.execCommand('copy');
    alert('{{ __forms("form.shortcode_copied") }}');
}

document.getElementById('newFieldLabel')?.addEventListener('input', function() {
    const nameInput = document.getElementById('newFieldName');
    if (!nameInput.dataset.manual) {
        nameInput.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/[\s]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    }
});
</script>
@endpush
