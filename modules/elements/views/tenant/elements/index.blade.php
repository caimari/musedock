@extends('layouts::app')

@section('title', $title ?? __element('element.my_elements'))

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>{{ $title ?? __element('element.my_elements') }}</h2>
                <p class="text-muted mb-0">{{ __element('element.manage_elements') }}</p>
            </div>
            <a href="{{ route('tenant.elements.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> {{ __element('element.create') }}
            </a>
        </div>

        <!-- Alerts with SweetAlert2 -->
        @if(session('success'))
            @push('scripts')
            <script>
                Swal.fire({
                    icon: 'success',
                    title: '{{ __element("element.success") }}',
                    text: '{{ session("success") }}',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
            @endpush
        @endif

        @if(session('error'))
            @push('scripts')
            <script>
                Swal.fire({
                    icon: 'error',
                    title: '{{ __element("element.error") }}',
                    html: '{!! addslashes(session("error")) !!}',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            </script>
            @endpush
        @endif

        <!-- Elements List -->
        @if(!empty($elements))
            <div class="row g-4">
                @foreach($elements as $element)
                    @php $isOwner = $element->tenant_id !== null; @endphp
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 {{ !$isOwner ? 'border-info' : '' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-{{ $element->is_active ? 'success' : 'secondary' }}">
                                        {{ $element->is_active ? __element('element.active') : __element('element.inactive') }}
                                    </span>
                                    @if(!$isOwner)
                                        <span class="badge bg-info" title="{{ __element('element.global') }}">
                                            <i class="bi bi-globe"></i>
                                        </span>
                                    @endif
                                </div>
                                <h5 class="card-title mb-1">{{ $element->name }}</h5>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-link-45deg"></i> {{ $element->slug }}
                                </p>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-{{
                                            $element->type === 'hero' ? 'card-heading' :
                                            ($element->type === 'faq' ? 'question-circle' :
                                            ($element->type === 'cta' ? 'megaphone' : 'box'))
                                        }}"></i>
                                        {{ $types[$element->type] ?? $element->type }}
                                    </span>
                                </div>
                                @if($element->description)
                                    <p class="text-muted small mb-0">{{ \Str::limit($element->description, 80) }}</p>
                                @endif
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex gap-2">
                                    @if($isOwner)
                                        <a href="{{ route('tenant.elements.edit', ['id' => $element->id]) }}"
                                           class="btn btn-sm btn-outline-primary flex-grow-1">
                                            <i class="bi bi-pencil"></i> {{ __element('element.edit') }}
                                        </a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete({{ $element->id }}, '{{ e($element->name) }}')"
                                                title="{{ __element('element.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <a href="{{ route('tenant.elements.edit', ['id' => $element->id]) }}"
                                           class="btn btn-sm btn-outline-info flex-grow-1">
                                            <i class="bi bi-eye"></i> {{ __element('element.view') }}
                                        </a>
                                    @endif
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="copyShortcode('{{ $element->id }}', '{{ $element->slug }}')"
                                            title="{{ __element('element.copy_shortcode') }}">
                                        <i class="bi bi-code-slash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-grid-3x3-gap display-1 text-muted mb-3"></i>
                    <h4>{{ __element('element.no_elements') }}</h4>
                    <p class="text-muted mb-4">{{ __element('element.no_elements_desc') }}</p>
                    <a href="{{ route('tenant.elements.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> {{ __element('element.create_first') }}
                    </a>
                </div>
            </div>
        @endif

        <!-- Usage Info -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>{{ __element('element.usage_info') }}</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">{{ __element('element.shortcode_info') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[element id=1]</code>
                            <small class="d-block text-muted mt-1">{{ __element('element.shortcode_by_id') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <code>[hero slug="my-hero"]</code>
                            <small class="d-block text-muted mt-1">{{ __element('element.shortcode_by_slug') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyShortcode(id, slug) {
    const text = `[element id="${id}"]`;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: '{{ __element("element.copied") }}',
            text: text,
            timer: 2000,
            showConfirmButton: false
        });
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: '{{ __element("element.confirm_delete") }}',
        text: '{{ __element("element.delete_warning") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '{{ __element("element.delete") }}',
        cancelButtonText: '{{ __element("element.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/{{ admin_path() }}/elements/${id}/delete`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
@endpush
@endsection
