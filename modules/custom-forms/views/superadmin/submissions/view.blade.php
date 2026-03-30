@extends('layouts::app')

@section('title', __forms('submission.view_submission'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('custom-forms.index') }}">{{ __forms('form.forms') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('custom-forms.submissions', $form->id) }}">{{ $form->name }}</a></li>
                    <li class="breadcrumb-item active">{{ __forms('submission.submission') }} #{{ $submission->id }}</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-envelope-open me-2"></i>
                    {{ __forms('submission.submission') }} #{{ $submission->id }}
                </h2>
                <div class="d-flex gap-2">
                    @if($prevSubmission)
                        <a href="{{ route('custom-forms.submissions.view', $prevSubmission) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-chevron-left"></i> {{ __forms('submission.previous') }}
                        </a>
                    @endif
                    @if($nextSubmission)
                        <a href="{{ route('custom-forms.submissions.view', $nextSubmission) }}" class="btn btn-outline-secondary">
                            {{ __forms('submission.next') }} <i class="bi bi-chevron-right"></i>
                        </a>
                    @endif
                </div>
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
                <!-- Submission Data -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __forms('submission.data') }}</h5>
                        <button type="button" class="btn btn-sm btn-outline-{{ $submission->is_starred ? 'warning' : 'secondary' }}" onclick="toggleStar()">
                            <i class="bi bi-star{{ $submission->is_starred ? '-fill' : '' }}"></i>
                            {{ $submission->is_starred ? __forms('submission.starred') : __forms('submission.star') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <tbody>
                                @foreach($fields as $field)
                                <tr>
                                    <th style="width: 30%; background: #f8f9fa;">{{ $field->field_label }}</th>
                                    <td>
                                        @php
                                            $value = $submission->data[$field->field_name] ?? null;
                                        @endphp
                                        @if(is_array($value))
                                            <ul class="mb-0 ps-3">
                                                @foreach($value as $item)
                                                    <li>{{ $item }}</li>
                                                @endforeach
                                            </ul>
                                        @elseif($field->field_type === 'checkbox')
                                            @if($value)
                                                <span class="badge bg-success">{{ __forms('email.yes') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __forms('email.no') }}</span>
                                            @endif
                                        @elseif($field->field_type === 'file' && $value)
                                            <a href="{{ $value }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark me-1"></i> {{ __forms('submission.download_file') }}
                                            </a>
                                        @elseif($field->field_type === 'email' && $value)
                                            <a href="mailto:{{ $value }}">{{ $value }}</a>
                                        @elseif($field->field_type === 'url' && $value)
                                            <a href="{{ $value }}" target="_blank">{{ $value }}</a>
                                        @elseif($field->field_type === 'textarea')
                                            <div class="text-pre-wrap">{{ $value ?: '-' }}</div>
                                        @else
                                            {{ $value ?: '-' }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-sticky me-2"></i>{{ __forms('submission.notes') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('custom-forms.submissions.update', $submission->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <textarea class="form-control" name="notes" rows="3" placeholder="{{ __forms('submission.notes_placeholder') }}">{{ $submission->notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-save me-1"></i> {{ __forms('submission.save_notes') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Meta Info -->
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __forms('submission.info') }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th class="text-muted">{{ __forms('submission.date') }}</th>
                                <td>{{ date('d/m/Y H:i:s', strtotime($submission->created_at)) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">{{ __forms('submission.ip') }}</th>
                                <td><code>{{ $submission->ip_address }}</code></td>
                            </tr>
                            <tr>
                                <th class="text-muted">{{ __forms('submission.user_agent') }}</th>
                                <td><small class="text-muted">{{ $submission->user_agent ?: '-' }}</small></td>
                            </tr>
                            @if($submission->page_url)
                            <tr>
                                <th class="text-muted">{{ __forms('submission.page') }}</th>
                                <td><a href="{{ $submission->page_url }}" target="_blank">{{ $submission->page_url }}</a></td>
                            </tr>
                            @endif
                            @if($submission->referrer_url)
                            <tr>
                                <th class="text-muted">{{ __forms('submission.referrer') }}</th>
                                <td><a href="{{ $submission->referrer_url }}" target="_blank">{{ $submission->referrer_url }}</a></td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Status -->
                <div class="card mt-4">
                    <div class="card-header"><h5 class="mb-0">{{ __forms('submission.status') }}</h5></div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between">
                                <span>{{ __forms('submission.read_status') }}</span>
                                @if($submission->is_read)
                                    <span class="badge bg-success">{{ __forms('submission.read') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ __forms('submission.unread') }}</span>
                                @endif
                            </div>
                            @if($submission->email_sent)
                            <div class="d-flex justify-content-between">
                                <span>{{ __forms('submission.email_sent') }}</span>
                                <span class="badge bg-success"><i class="bi bi-check"></i></span>
                            </div>
                            @endif
                            @if($submission->is_spam)
                            <div class="d-flex justify-content-between">
                                <span>{{ __forms('submission.spam_status') }}</span>
                                <span class="badge bg-danger">{{ __forms('submission.spam') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if(!$submission->is_spam)
                            <button type="button" class="btn btn-outline-warning" onclick="markSpam()">
                                <i class="bi bi-exclamation-triangle me-1"></i> {{ __forms('submission.mark_spam') }}
                            </button>
                            @else
                            <button type="button" class="btn btn-outline-success" onclick="unmarkSpam()">
                                <i class="bi bi-check-circle me-1"></i> {{ __forms('submission.unmark_spam') }}
                            </button>
                            @endif
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash me-1"></i> {{ __forms('submission.delete') }}
                            </button>
                            <a href="{{ route('custom-forms.submissions', $form->id) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> {{ __forms('submission.back_to_list') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __forms('submission.confirm_delete') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{{ __forms('submission.confirm_delete_msg') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __forms('form.cancel') }}</button>
                <form action="{{ route('custom-forms.submissions.delete', $submission->id) }}" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __forms('submission.delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleStar() {
    fetch(`/admin/custom-forms/submissions/{{ $submission->id }}/star`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function markSpam() {
    fetch(`/admin/custom-forms/submissions/{{ $submission->id }}/spam`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function unmarkSpam() {
    fetch(`/admin/custom-forms/submissions/{{ $submission->id }}/unspam`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}
</script>
@endpush

<style>
.text-pre-wrap {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
