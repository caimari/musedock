@extends('layouts::app')

@section('title', __forms('submission.submissions_for', ['form' => $form->name]))

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
                    <li class="breadcrumb-item"><a href="{{ route('tenant.custom-forms.edit', $form->id) }}">{{ $form->name }}</a></li>
                    <li class="breadcrumb-item active">{{ __forms('submission.submissions') }}</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-inbox me-2"></i>{{ __forms('submission.submissions') }}: {{ $form->name }}</h2>
                <div>
                    <a href="{{ route('tenant.custom-forms.submissions.export', $form->id) }}" class="btn btn-outline-success me-2">
                        <i class="bi bi-download me-1"></i> {{ __forms('submission.export') }}
                    </a>
                    <a href="{{ route('tenant.custom-forms.edit', $form->id) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i> {{ __forms('form.edit') }}
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.status') }}</label>
                        <select name="status" class="form-select">
                            <option value="">{{ __forms('submission.all') }}</option>
                            <option value="unread" {{ request('status') == 'unread' ? 'selected' : '' }}>{{ __forms('submission.unread') }}</option>
                            <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>{{ __forms('submission.read') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.date_from') }}</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __forms('submission.date_to') }}</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> {{ __forms('submission.filter') }}</button>
                            <a href="{{ route('tenant.custom-forms.submissions', $form->id) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __forms('submission.summary') }}</th>
                                <th style="width: 150px;">{{ __forms('submission.date') }}</th>
                                <th style="width: 100px;">{{ __forms('submission.status') }}</th>
                                <th style="width: 100px;">{{ __forms('submission.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions ?? [] as $submission)
                            <tr class="{{ $submission->is_read ? '' : 'table-light fw-bold' }}">
                                <td>
                                    <a href="{{ route('tenant.custom-forms.submissions.view', $submission->id) }}" class="text-decoration-none">
                                        @php
                                            $data = $submission->getFormattedData();
                                            $preview = [];
                                            $count = 0;
                                            foreach ($data as $key => $value) {
                                                if ($count >= 3) break;
                                                if (is_string($value) && strlen($value) > 50) $value = substr($value, 0, 50) . '...';
                                                $preview[] = $value;
                                                $count++;
                                            }
                                        @endphp
                                        {{ implode(' | ', $preview) ?: __forms('submission.empty') }}
                                    </a>
                                </td>
                                <td>
                                    <small class="text-muted">{{ date('d/m/Y H:i', strtotime($submission->created_at)) }}</small>
                                </td>
                                <td>
                                    @if($submission->is_read)
                                        <span class="badge bg-secondary">{{ __forms('submission.read') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __forms('submission.new') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('tenant.custom-forms.submissions.view', $submission->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form action="{{ route('tenant.custom-forms.submissions.delete', $submission->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __forms('submission.confirm_delete_msg') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <p class="mt-3 mb-0 text-muted">{{ __forms('submission.no_submissions') }}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($pagination) && $pagination['total_pages'] > 1)
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">
                    {{ __forms('submission.showing', ['from' => $pagination['from'], 'to' => $pagination['to'], 'total' => $pagination['total']]) }}
                </span>
                <nav>
                    <ul class="pagination mb-0">
                        @for($i = 1; $i <= $pagination['total_pages']; $i++)
                            <li class="page-item {{ $i == $pagination['current_page'] ? 'active' : '' }}">
                                <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
                            </li>
                        @endfor
                    </ul>
                </nav>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
