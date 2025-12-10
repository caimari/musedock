@extends('layouts::app')

@section('title', __forms('submission.submissions_for', ['form' => $form->name]))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('custom-forms.index') }}">{{ __forms('form.forms') }}</a>
                <span class="mx-2">/</span>
                <a href="{{ route('custom-forms.edit', $form->id) }}">{{ $form->name }}</a>
                <span class="mx-2">/</span>
                <span>{{ __forms('submission.submissions') }}</span>
            </div>
            <div>
                <a href="{{ route('custom-forms.submissions.export', ['formId' => $form->id]) }}" class="btn btn-sm btn-outline-success me-2">
                    <i class="bi bi-download me-1"></i> {{ __forms('submission.export') }}
                </a>
                <a href="{{ route('custom-forms.edit', $form->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> {{ __forms('form.edit') }}
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.status') }}</label>
                        <select name="status" class="form-select">
                            <option value="">{{ __forms('submission.all') }}</option>
                            <option value="unread" {{ ($_GET['status'] ?? '') == 'unread' ? 'selected' : '' }}>{{ __forms('submission.unread') }}</option>
                            <option value="read" {{ ($_GET['status'] ?? '') == 'read' ? 'selected' : '' }}>{{ __forms('submission.read') }}</option>
                            <option value="starred" {{ ($_GET['status'] ?? '') == 'starred' ? 'selected' : '' }}>{{ __forms('submission.starred') }}</option>
                            <option value="spam" {{ ($_GET['status'] ?? '') == 'spam' ? 'selected' : '' }}>{{ __forms('submission.spam') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.date_from') }}</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $_GET['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.date_to') }}</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $_GET['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> {{ __forms('submission.filter') }}
                            </button>
                            <a href="{{ route('custom-forms.submissions', $form->id) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th style="width: 40px;"></th>
                                <th>{{ __forms('submission.summary') }}</th>
                                <th style="width: 150px;">{{ __forms('submission.date') }}</th>
                                <th style="width: 120px;">{{ __forms('submission.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions ?? [] as $submission)
                            <tr class="{{ $submission->is_read ? '' : 'table-light fw-bold' }}">
                                <td>
                                    <input type="checkbox" class="form-check-input submission-check" value="{{ $submission->id }}">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toggleStar({{ $submission->id }})">
                                        <i class="bi bi-star{{ $submission->is_starred ? '-fill text-warning' : ' text-muted' }}"></i>
                                    </button>
                                </td>
                                <td>
                                    <a href="{{ route('custom-forms.submissions.view', $submission->id) }}" class="text-decoration-none">
                                        <div class="submission-summary">
                                            @php
                                                $data = $submission->getFormattedData();
                                                $preview = [];
                                                $count = 0;
                                                foreach ($data as $key => $value) {
                                                    if ($count >= 3) break;
                                                    if (is_string($value) && strlen($value) > 50) {
                                                        $value = substr($value, 0, 50) . '...';
                                                    }
                                                    $preview[] = $value;
                                                    $count++;
                                                }
                                            @endphp
                                            {{ implode(' | ', $preview) ?: __forms('submission.empty') }}
                                        </div>
                                    </a>
                                    @if($submission->is_spam)
                                        <span class="badge bg-warning text-dark">{{ __forms('submission.spam') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ date('d/m/Y', strtotime($submission->created_at)) }}<br>
                                        {{ date('H:i', strtotime($submission->created_at)) }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('custom-forms.submissions.view', $submission->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __forms('submission.view') }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSubmission({{ $submission->id }})" title="{{ __forms('submission.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <p class="mt-3 mb-0 text-muted">{{ __forms('submission.no_submissions') }}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($pagination) && $pagination['last_page'] > 1)
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">
                    {{ __forms('submission.showing', ['from' => $pagination['from'], 'to' => $pagination['to'], 'total' => $pagination['total']]) }}
                </span>
                <nav>
                    <ul class="pagination mb-0">
                        @if($pagination['current_page'] > 1)
                            <li class="page-item">
                                <a class="page-link" href="?page={{ $pagination['current_page'] - 1 }}">«</a>
                            </li>
                        @endif
                        @for($i = 1; $i <= $pagination['last_page']; $i++)
                            <li class="page-item {{ $i == $pagination['current_page'] ? 'active' : '' }}">
                                <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
                            </li>
                        @endfor
                        @if($pagination['current_page'] < $pagination['last_page'])
                            <li class="page-item">
                                <a class="page-link" href="?page={{ $pagination['current_page'] + 1 }}">»</a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
            @endif
        </div>

        <!-- Bulk Actions -->
        <div class="position-fixed bottom-0 start-50 translate-middle-x mb-4 bulk-actions" style="display: none;" id="bulkActions">
            <div class="card shadow-lg">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <span class="text-muted"><span id="selectedCount">0</span> {{ __forms('submission.selected') }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkMarkRead()">
                        <i class="bi bi-envelope-open me-1"></i> {{ __forms('submission.mark_read') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="bulkMarkSpam()">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ __forms('submission.mark_spam') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                        <i class="bi bi-trash me-1"></i> {{ __forms('submission.delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
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
                <form id="deleteForm" method="POST" style="display: inline;">
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
const formId = {{ $form->id }};

// Select all
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.submission-check').forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

// Individual checkbox
document.querySelectorAll('.submission-check').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checked = document.querySelectorAll('.submission-check:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkActions').style.display = checked > 0 ? 'block' : 'none';
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.submission-check:checked')).map(cb => cb.value);
}

function toggleStar(id) {
    fetch(`/admin/custom-forms/submissions/${id}/star`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

function deleteSubmission(id) {
    document.getElementById('deleteForm').action = `/admin/custom-forms/submissions/${id}`;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function bulkMarkRead() {
    bulkAction('read');
}

function bulkMarkSpam() {
    bulkAction('spam');
}

function bulkDelete() {
    if (!confirm('{{ __forms("submission.confirm_bulk_delete") }}')) return;
    bulkAction('delete');
}

function bulkAction(action) {
    fetch(`/admin/custom-forms/submissions/bulk`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            ids: getSelectedIds(),
            action: action
        })
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}
</script>
@endpush
