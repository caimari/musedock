@extends('layouts::app')

@section('title', __forms('submission.view_submission'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <a href="{{ route('tenant.custom-forms.index') }}">{{ __forms('form.forms') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('tenant.custom-forms.submissions', $form->id) }}">{{ $form->name }}</a>
                        </li>
                        <li class="breadcrumb-item active">{{ __forms('submission.submission') }} #{{ $submission->id }}</li>
                    </ol>
                </nav>
                <h2 class="mb-0"><i class="bi bi-envelope-open me-2"></i>{{ __forms('submission.submission') }} #{{ $submission->id }}</h2>
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __forms('submission.data') }}</h5>
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
                                        @elseif($field->field_type === 'textarea')
                                            <div style="white-space: pre-wrap;">{{ $value ?: '-' }}</div>
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
            </div>

            <div class="col-lg-4">
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
                            @if($submission->page_url)
                            <tr>
                                <th class="text-muted">{{ __forms('submission.page') }}</th>
                                <td><a href="{{ $submission->page_url }}" target="_blank">{{ parse_url($submission->page_url, PHP_URL_PATH) }}</a></td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form action="{{ route('tenant.custom-forms.submissions.delete', $submission->id) }}" method="POST" onsubmit="return confirm('{{ __forms('submission.confirm_delete_msg') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-trash me-1"></i> {{ __forms('submission.delete') }}
                                </button>
                            </form>
                            <a href="{{ route('tenant.custom-forms.submissions', $form->id) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> {{ __forms('submission.back_to_list') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
