@extends('layouts::app')

@section('title', __forms('submission.submissions'))

@push('styles')
<link rel="stylesheet" href="/modules/custom-forms/css/admin.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-inbox me-2"></i>{{ __forms('submission.submissions') }}</h2>
                <p class="text-muted mb-0">{{ __forms('submission.all_forms_desc') }}</p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            @forelse($forms ?? [] as $form)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0">{{ $form->name }}</h5>
                            @if($form->unread_count > 0)
                                <span class="badge bg-danger">{{ $form->unread_count }} {{ __forms('submission.new') }}</span>
                            @endif
                        </div>
                        <p class="text-muted small mb-3">{{ $form->description ?: __forms('form.no_description') }}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">
                                <i class="bi bi-envelope me-1"></i> {{ $form->submission_count ?? 0 }} {{ __forms('submission.total') }}
                            </span>
                            <a href="{{ route('custom-forms.submissions.list', ['formId' => $form->id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i> {{ __forms('submission.view') }}
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">
                            {{ __forms('submission.last_submission') }}:
                            {{ $form->last_submission_at ? date('d/m/Y H:i', strtotime($form->last_submission_at)) : __forms('submission.none') }}
                        </small>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="mt-3">{{ __forms('submission.no_forms') }}</h4>
                        <p class="text-muted">{{ __forms('submission.create_form_first') }}</p>
                        <a href="{{ route('custom-forms.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> {{ __forms('form.create') }}
                        </a>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
