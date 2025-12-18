@extends('layouts::app')

@section('title', $title ?? __element('element.edit'))

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="breadcrumb">
                <a href="{{ route('tenant.elements.index') }}">{{ __element('element.elements') }}</a> <span class="mx-2">/</span> <span>{{ $element->name }}</span>
            </div>
            <a href="{{ route('tenant.elements.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __element('element.back') }}
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

        @if($isReadOnly ?? false)
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i>{{ __element('element.global_readonly') }}
            </div>
        @endif

        <!-- Form -->
        <form action="{{ route('tenant.elements.update', ['id' => $element->id]) }}" method="POST" id="elementForm">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- Basic Info -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.basic_info') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">{{ __element('element.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $element->name) }}" required {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>
                            <div class="mb-3">
                                <label for="slug" class="form-label">{{ __element('element.slug') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $element->slug) }}" pattern="[a-z0-9\-]+" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div class="form-text">
                                    {{ __element('element.slug_help') }}
                                    <span id="slug-check-result" class="ms-2"></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">{{ __element('element.description') }}</label>
                                <textarea class="form-control" id="description" name="description" rows="3" {{ $isReadOnly ? 'disabled' : '' }}>{{ old('description', $element->description) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">{{ __element('element.type') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required onchange="updateLayoutOptions()" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">{{ __element('element.type_select') }}</option>
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}" {{ old('type', $element->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Settings -->
                    <div class="card mt-4" id="layoutCard">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.layout_settings') }}</h5>
                        </div>
                        <div class="card-body">
                            @php $currentLayout = old('layout_type', $element->layout_type); @endphp

                            <div id="heroLayoutOptions" style="display: {{ $element->type === 'hero' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($heroLayouts as $key => $label)
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="hero_layout_{{ $key }}" value="{{ $key }}" {{ $currentLayout === $key ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                            <label class="btn btn-outline-primary w-100" for="hero_layout_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div id="faqLayoutOptions" style="display: {{ $element->type === 'faq' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($faqLayouts as $key => $label)
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="faq_layout_{{ $key }}" value="{{ $key }}" {{ $currentLayout === $key ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                            <label class="btn btn-outline-primary w-100" for="faq_layout_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div id="ctaLayoutOptions" style="display: {{ $element->type === 'cta' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($ctaLayouts as $key => $label)
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="cta_layout_{{ $key }}" value="{{ $key }}" {{ $currentLayout === $key ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                            <label class="btn btn-outline-primary w-100" for="cta_layout_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Fields -->
                    <div class="card mt-4" id="contentCard">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.content') }}</h5>
                        </div>
                        <div class="card-body" id="contentFields">
                            @php $data = $element->getData(); @endphp

                            @if($element->type === 'hero')
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('hero.heading') }}</label>
                                    <input type="text" class="form-control" name="data[heading]" value="{{ old('data.heading', $data['heading'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('hero.subheading') }}</label>
                                    <input type="text" class="form-control" name="data[subheading]" value="{{ old('data.subheading', $data['subheading'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('hero.description') }}</label>
                                    <textarea class="form-control" name="data[description]" rows="3" {{ $isReadOnly ? 'disabled' : '' }}>{{ old('data.description', $data['description'] ?? '') }}</textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __element('hero.button_text') }}</label>
                                        <input type="text" class="form-control" name="data[button_text]" value="{{ old('data.button_text', $data['button_text'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __element('hero.button_url') }}</label>
                                        <input type="text" class="form-control" name="data[button_url]" value="{{ old('data.button_url', $data['button_url'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label class="form-label">{{ __element('hero.image_url') }}</label>
                                    @if(!$isReadOnly)
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="hero_image_url_edit" name="data[image_url]" value="{{ old('data.image_url', $data['image_url'] ?? '') }}" placeholder="URL de la imagen">
                                            <button type="button" class="btn btn-outline-secondary open-media-modal-button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#mediaManagerModal"
                                                    data-input-target="#hero_image_url_edit"
                                                    data-preview-target="#hero_image_preview_edit">
                                                <i class="bi bi-image me-1"></i> Seleccionar Imagen
                                            </button>
                                        </div>
                                        <img id="hero_image_preview_edit" src="{{ old('data.image_url', $data['image_url'] ?? '') }}" class="img-fluid rounded border mt-2" style="max-height: 150px; {{ (old('data.image_url', $data['image_url'] ?? '') ? '' : 'display: none;') }}">
                                    @else
                                        <input type="text" class="form-control" name="data[image_url]" value="{{ old('data.image_url', $data['image_url'] ?? '') }}" disabled>
                                        @if(old('data.image_url', $data['image_url'] ?? ''))
                                            <img src="{{ old('data.image_url', $data['image_url'] ?? '') }}" class="img-fluid rounded border mt-2" style="max-height: 150px;">
                                        @endif
                                    @endif
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('hero.image_alt') }}</label>
                                    <input type="text" class="form-control" name="data[image_alt]" value="{{ old('data.image_alt', $data['image_alt'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>

                            @elseif($element->type === 'faq')
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('faq.heading') }}</label>
                                    <input type="text" class="form-control" name="data[heading]" value="{{ old('data.heading', $data['heading'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div id="faqItems">
                                    <label class="form-label">{{ __element('faq.items') }}</label>
                                    @php $items = $data['items'] ?? []; @endphp
                                    @foreach($items as $index => $item)
                                        <div class="faq-item border rounded p-3 mb-3">
                                            <div class="mb-2">
                                                <input type="text" class="form-control" name="data[items][{{ $index }}][question]" value="{{ $item['question'] ?? '' }}" placeholder="{{ __element('faq.question_placeholder') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                            </div>
                                            <textarea class="form-control" name="data[items][{{ $index }}][answer]" rows="2" placeholder="{{ __element('faq.answer_placeholder') }}" {{ $isReadOnly ? 'disabled' : '' }}>{{ $item['answer'] ?? '' }}</textarea>
                                            @if(!$isReadOnly)
                                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="this.closest('.faq-item').remove()">
                                                    <i class="bi bi-trash"></i> {{ __element('faq.remove_item') }}
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @if(!$isReadOnly)
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addFaqItem()">
                                        <i class="bi bi-plus-lg"></i> {{ __element('faq.add_item') }}
                                    </button>
                                @endif

                            @elseif($element->type === 'cta')
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('cta.heading') }}</label>
                                    <input type="text" class="form-control" name="data[heading]" value="{{ old('data.heading', $data['heading'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __element('cta.text') }}</label>
                                    <textarea class="form-control" name="data[text]" rows="2" {{ $isReadOnly ? 'disabled' : '' }}>{{ old('data.text', $data['text'] ?? '') }}</textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __element('cta.button_text') }}</label>
                                        <input type="text" class="form-control" name="data[button_text]" value="{{ old('data.button_text', $data['button_text'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __element('cta.button_url') }}</label>
                                        <input type="text" class="form-control" name="data[button_url]" value="{{ old('data.button_url', $data['button_url'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

                <div class="col-lg-4">
                    <!-- Shortcode -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.shortcode') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" value='[element id="{{ $element->id }}"]' readonly>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyToClipboard('[element id=&quot;{{ $element->id }}&quot;]')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" value='[{{ $element->type }} slug="{{ $element->slug }}"]' readonly>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyToClipboard('[{{ $element->type }} slug=&quot;{{ $element->slug }}&quot;]')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.status') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ old('is_active', $element->is_active) ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                <label class="form-check-label" for="is_active">{{ __element('element.is_active') }}</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="featured" name="featured" {{ old('featured', $element->featured) ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                <label class="form-check-label" for="featured">{{ __element('element.featured') }}</label>
                            </div>
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">{{ __element('element.sort_order') }}</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $element->sort_order) }}" {{ $isReadOnly ? 'disabled' : '' }}>
                            </div>
                        </div>
                        @if(!$isReadOnly)
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-save me-1"></i> {{ __element('element.save') }}
                                </button>
                                <a href="{{ route('tenant.elements.index') }}" class="btn btn-outline-secondary w-100 mt-2">
                                    {{ __element('element.cancel') }}
                                </a>
                            </div>
                        @else
                            <div class="card-footer">
                                <a href="{{ route('tenant.elements.index') }}" class="btn btn-outline-secondary w-100">
                                    {{ __element('element.back') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $modalPath = realpath(__DIR__ . '/../../../media-manager/views/admin/_modal.blade.php');
    if ($modalPath && file_exists($modalPath)) {
        include $modalPath;
    }
@endphp

@push('scripts')
<script>
let faqItemCount = {{ count($data['items'] ?? []) }};

function addFaqItem() {
    const container = document.getElementById('faqItems');
    const newItem = document.createElement('div');
    newItem.className = 'faq-item border rounded p-3 mb-3';
    newItem.innerHTML = `
        <div class="mb-2">
            <input type="text" class="form-control" name="data[items][${faqItemCount}][question]" placeholder="{{ __element('faq.question_placeholder') }}">
        </div>
        <textarea class="form-control" name="data[items][${faqItemCount}][answer]" rows="2" placeholder="{{ __element('faq.answer_placeholder') }}"></textarea>
        <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="this.closest('.faq-item').remove()">
            <i class="bi bi-trash"></i> {{ __element('faq.remove_item') }}
        </button>
    `;
    container.appendChild(newItem);
    faqItemCount++;
}

function copyToClipboard(text) {
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

function updateLayoutOptions() {
    const type = document.getElementById('type').value;
    const layoutOptions = {
        'hero': 'heroLayoutOptions',
        'faq': 'faqLayoutOptions',
        'cta': 'ctaLayoutOptions'
    };

    Object.values(layoutOptions).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    if (type && layoutOptions[type]) {
        const layoutEl = document.getElementById(layoutOptions[type]);
        if (layoutEl) layoutEl.style.display = 'block';
    }
}

// Slug validation
let slugTimeout = null;
const nameInput = document.getElementById('name');
const slugInput = document.getElementById('slug');
const slugResult = document.getElementById('slug-check-result');
const elementId = {{ $element->id }};
const csrfToken = '{{ csrf_token() }}';

function slugify(text) {
    return text.toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
        .replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, '');
}

function checkSlugAvailability(slug) {
    if (!slug || !slugResult) return;

    clearTimeout(slugTimeout);
    slugTimeout = setTimeout(() => {
        const formData = new FormData();
        formData.append('slug', slug);
        formData.append('exclude_id', elementId);
        formData.append('_token', csrfToken);

        fetch('{{ route("tenant.elements.check-slug") }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.available) {
                slugResult.innerHTML = '<i class="bi bi-check-circle text-success"></i> Disponible';
                slugResult.className = 'ms-2 text-success';
            } else {
                slugResult.innerHTML = '<i class="bi bi-x-circle text-danger"></i> ' + data.message;
                slugResult.className = 'ms-2 text-danger';
            }
        })
        .catch(() => {
            slugResult.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i> Error';
            slugResult.className = 'ms-2 text-warning';
        });
    }, 500);
}

if (nameInput && !{{ $isReadOnly ? 'true' : 'false' }}) {
    nameInput.addEventListener('input', function() {
        if (!slugInput.dataset.manual) {
            const autoSlug = slugify(this.value);
            slugInput.value = autoSlug;
            checkSlugAvailability(autoSlug);
        }
    });
}

if (slugInput && !{{ $isReadOnly ? 'true' : 'false' }}) {
    slugInput.addEventListener('input', function() {
        const clean = slugify(this.value);
        if (this.value !== clean) this.value = clean;
        slugInput.dataset.manual = '1';
        checkSlugAvailability(clean);
    });
}
</script>
@endpush
@endsection
