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
                                <label for="type" class="form-label">{{ __element('element.type') }}</label>
                                <input type="text" class="form-control" value="{{ $types[$element->type] ?? $element->type }}" disabled>
                                <input type="hidden" name="type" value="{{ $element->type }}">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>{{ __element('element.type_locked') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Settings -->
                    <div class="card mt-4" id="layoutCard">
                        <div class="card-header">
                            <h5 class="mb-0">{{ __element('element.layout_settings') }}</h5>
                        </div>
                        <div class="card-body">
                            @php
                                $currentLayout = old('layout_type');
                                if ($currentLayout === null || $currentLayout === '') {
                                    $currentLayout = $element->layout_type ?? 'image-right';
                                }
                                $currentLayout = is_string($currentLayout) ? trim($currentLayout) : (string)$currentLayout;
                            @endphp

                            <div id="heroLayoutOptions" style="display: {{ $element->type === 'hero' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($heroLayouts as $key => $label)
                                        @php $isChecked = (trim((string)$currentLayout) === trim((string)$key)); @endphp
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="hero_layout_{{ $key }}" value="{{ $key }}" {{ $isChecked ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                            <label class="btn btn-outline-primary w-100" for="hero_layout_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div id="faqLayoutOptions" style="display: {{ $element->type === 'faq' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($faqLayouts as $key => $label)
                                        @php $isChecked = (trim((string)$currentLayout) === trim((string)$key)); @endphp
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="faq_layout_{{ $key }}" value="{{ $key }}" {{ $isChecked ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                            <label class="btn btn-outline-primary w-100" for="faq_layout_{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div id="ctaLayoutOptions" style="display: {{ $element->type === 'cta' ? 'block' : 'none' }};">
                                <label class="form-label">{{ __element('element.layout_type') }}</label>
                                <div class="row g-2 mb-3">
                                    @foreach($ctaLayouts as $key => $label)
                                        @php $isChecked = (trim((string)$currentLayout) === trim((string)$key)); @endphp
                                        <div class="col-6 col-md-4">
                                            <input type="radio" class="btn-check" name="layout_type" id="cta_layout_{{ $key }}" value="{{ $key }}" {{ $isChecked ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
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
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Destino del boton</label>
                                        <select class="form-select" name="data[button_target]" {{ $isReadOnly ? 'disabled' : '' }}>
                                            @php $buttonTarget = old('data.button_target', $data['button_target'] ?? '_self'); @endphp
                                            <option value="_self" {{ $buttonTarget === '_self' ? 'selected' : '' }}>Misma pestana</option>
                                            <option value="_blank" {{ $buttonTarget === '_blank' ? 'selected' : '' }}>Nueva pestana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Color fondo boton</label>
                                        <input type="color" class="form-control form-control-color" name="data[button_bg_color]" value="{{ old('data.button_bg_color', $data['button_bg_color'] ?? '#0f172a') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Color texto boton</label>
                                        <input type="color" class="form-control form-control-color" name="data[button_text_color]" value="{{ old('data.button_text_color', $data['button_text_color'] ?? '#ffffff') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>

                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Texto segundo boton</label>
                                        <input type="text" class="form-control" name="data[button_secondary_text]" value="{{ old('data.button_secondary_text', $data['button_secondary_text'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">URL segundo boton</label>
                                        <input type="text" class="form-control" name="data[button_secondary_url]" value="{{ old('data.button_secondary_url', $data['button_secondary_url'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Destino segundo boton</label>
                                        <select class="form-select" name="data[button_secondary_target]" {{ $isReadOnly ? 'disabled' : '' }}>
                                            @php $buttonSecondaryTarget = old('data.button_secondary_target', $data['button_secondary_target'] ?? '_self'); @endphp
                                            <option value="_self" {{ $buttonSecondaryTarget === '_self' ? 'selected' : '' }}>Misma pestana</option>
                                            <option value="_blank" {{ $buttonSecondaryTarget === '_blank' ? 'selected' : '' }}>Nueva pestana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Color fondo segundo boton</label>
                                        <input type="color" class="form-control form-control-color" name="data[button_secondary_bg_color]" value="{{ old('data.button_secondary_bg_color', $data['button_secondary_bg_color'] ?? '#ffffff') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Color texto segundo boton</label>
                                        <input type="color" class="form-control form-control-color" name="data[button_secondary_text_color]" value="{{ old('data.button_secondary_text_color', $data['button_secondary_text_color'] ?? '#0f172a') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    </div>
                                </div>
                                <div class="row g-3 mt-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Tipo de media</label>
                                        @php $mediaType = old('data.media_type', $data['media_type'] ?? 'image'); @endphp
                                        <select class="form-select" name="data[media_type]" {{ $isReadOnly ? 'disabled' : '' }}>
                                            <option value="image" {{ $mediaType === 'image' ? 'selected' : '' }}>Imagen</option>
                                            <option value="video" {{ $mediaType === 'video' ? 'selected' : '' }}>Video</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">{{ __element('hero.video_url') }}</label>
                                        <input type="text" class="form-control" name="data[video_url]" value="{{ old('data.video_url', $data['video_url'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        <small class="text-muted">YouTube, Vimeo o MP4. Se usa si el tipo de media es Video.</small>
                                    </div>
                                </div>

                                <!-- Estilos personalizados -->
                                <div class="border rounded p-3 mt-4 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0"><i class="bi bi-palette me-2"></i>Colores personalizados</h6>
                                        @if(!$isReadOnly)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetColorsBtn">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
                                        </button>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label small">Color subtitulo</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[subheading_color]" id="subheading_color" value="{{ old('data.subheading_color', $data['subheading_color'] ?? '#64748b') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Color titulo</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[heading_color]" id="heading_color" value="{{ old('data.heading_color', $data['heading_color'] ?? '#0f172a') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Color descripcion</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[description_color]" id="description_color" value="{{ old('data.description_color', $data['description_color'] ?? '#475569') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Color diapositiva</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[card_bg_color]" id="card_bg_color" value="{{ old('data.card_bg_color', $data['card_bg_color'] ?? '#ffffff') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-2">
                                        <div class="col-md-3">
                                            <label class="form-label small">Fondo diapositiva (exterior)</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[card_wrapper_bg_color]" id="card_wrapper_bg_color" value="{{ old('data.card_wrapper_bg_color', $data['card_wrapper_bg_color'] ?? '#e2e8f0') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Color caption</label>
                                            <input type="color" class="form-control form-control-color w-100" name="data[caption_color]" id="caption_color" value="{{ old('data.caption_color', $data['caption_color'] ?? '#0f172a') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tipografias personalizadas -->
                                <div class="border rounded p-3 mt-4 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0"><i class="bi bi-fonts me-2"></i>Tipografías personalizadas</h6>
                                        @if(!$isReadOnly)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFontsBtn">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
                                        </button>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small">Tipografía subtítulo</label>
                                            <select class="form-select form-select-sm" name="data[subheading_font]" id="subheading_font" {{ $isReadOnly ? 'disabled' : '' }}>
                                                <option value="">Por defecto</option>
                                                <option value="Inter" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                                <option value="Roboto" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                                <option value="Open Sans" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                                <option value="Lato" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Lato' ? 'selected' : '' }}>Lato</option>
                                                <option value="Montserrat" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                                <option value="Poppins" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Poppins' ? 'selected' : '' }}>Poppins</option>
                                                <option value="Playfair Display" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                                <option value="Merriweather" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Merriweather' ? 'selected' : '' }}>Merriweather</option>
                                                <option value="Lora" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Lora' ? 'selected' : '' }}>Lora</option>
                                                <option value="Source Serif Pro" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Source Serif Pro' ? 'selected' : '' }}>Source Serif Pro</option>
                                                <option value="Raleway" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Raleway' ? 'selected' : '' }}>Raleway</option>
                                                <option value="Oswald" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Oswald' ? 'selected' : '' }}>Oswald</option>
                                                <option value="Dancing Script" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Dancing Script' ? 'selected' : '' }}>Dancing Script (cursiva)</option>
                                                <option value="Pacifico" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Pacifico' ? 'selected' : '' }}>Pacifico (cursiva)</option>
                                                <option value="Great Vibes" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Great Vibes' ? 'selected' : '' }}>Great Vibes (cursiva)</option>
                                                <option value="Caveat" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Caveat' ? 'selected' : '' }}>Caveat (manuscrita)</option>
                                                <option value="Satisfy" {{ old('data.subheading_font', $data['subheading_font'] ?? '') == 'Satisfy' ? 'selected' : '' }}>Satisfy (cursiva)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Estilo</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="checkbox" class="btn-check" name="data[subheading_italic]" id="subheading_italic" value="1" {{ old('data.subheading_italic', $data['subheading_italic'] ?? '') ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                                <label class="btn btn-outline-secondary btn-sm" for="subheading_italic" title="Cursiva"><i class="bi bi-type-italic"></i></label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small">Tipografía título</label>
                                            <select class="form-select form-select-sm" name="data[heading_font]" id="heading_font" {{ $isReadOnly ? 'disabled' : '' }}>
                                                <option value="">Por defecto</option>
                                                <option value="Inter" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                                <option value="Roboto" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                                <option value="Open Sans" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                                <option value="Lato" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Lato' ? 'selected' : '' }}>Lato</option>
                                                <option value="Montserrat" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                                <option value="Poppins" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Poppins' ? 'selected' : '' }}>Poppins</option>
                                                <option value="Playfair Display" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                                <option value="Merriweather" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Merriweather' ? 'selected' : '' }}>Merriweather</option>
                                                <option value="Lora" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Lora' ? 'selected' : '' }}>Lora</option>
                                                <option value="Source Serif Pro" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Source Serif Pro' ? 'selected' : '' }}>Source Serif Pro</option>
                                                <option value="Raleway" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Raleway' ? 'selected' : '' }}>Raleway</option>
                                                <option value="Oswald" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Oswald' ? 'selected' : '' }}>Oswald</option>
                                                <option value="Dancing Script" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Dancing Script' ? 'selected' : '' }}>Dancing Script (cursiva)</option>
                                                <option value="Pacifico" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Pacifico' ? 'selected' : '' }}>Pacifico (cursiva)</option>
                                                <option value="Great Vibes" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Great Vibes' ? 'selected' : '' }}>Great Vibes (cursiva)</option>
                                                <option value="Caveat" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Caveat' ? 'selected' : '' }}>Caveat (manuscrita)</option>
                                                <option value="Satisfy" {{ old('data.heading_font', $data['heading_font'] ?? '') == 'Satisfy' ? 'selected' : '' }}>Satisfy (cursiva)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Estilo</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="checkbox" class="btn-check" name="data[heading_italic]" id="heading_italic" value="1" {{ old('data.heading_italic', $data['heading_italic'] ?? '') ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                                <label class="btn btn-outline-secondary btn-sm" for="heading_italic" title="Cursiva"><i class="bi bi-type-italic"></i></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-2">
                                        <div class="col-md-4">
                                            <label class="form-label small">Tipografía descripción</label>
                                            <select class="form-select form-select-sm" name="data[description_font]" id="description_font" {{ $isReadOnly ? 'disabled' : '' }}>
                                                <option value="">Por defecto</option>
                                                <option value="Inter" {{ old('data.description_font', $data['description_font'] ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                                <option value="Roboto" {{ old('data.description_font', $data['description_font'] ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                                <option value="Open Sans" {{ old('data.description_font', $data['description_font'] ?? '') == 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                                <option value="Lato" {{ old('data.description_font', $data['description_font'] ?? '') == 'Lato' ? 'selected' : '' }}>Lato</option>
                                                <option value="Montserrat" {{ old('data.description_font', $data['description_font'] ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                                <option value="Poppins" {{ old('data.description_font', $data['description_font'] ?? '') == 'Poppins' ? 'selected' : '' }}>Poppins</option>
                                                <option value="Playfair Display" {{ old('data.description_font', $data['description_font'] ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                                <option value="Merriweather" {{ old('data.description_font', $data['description_font'] ?? '') == 'Merriweather' ? 'selected' : '' }}>Merriweather</option>
                                                <option value="Lora" {{ old('data.description_font', $data['description_font'] ?? '') == 'Lora' ? 'selected' : '' }}>Lora</option>
                                                <option value="Source Serif Pro" {{ old('data.description_font', $data['description_font'] ?? '') == 'Source Serif Pro' ? 'selected' : '' }}>Source Serif Pro</option>
                                                <option value="Raleway" {{ old('data.description_font', $data['description_font'] ?? '') == 'Raleway' ? 'selected' : '' }}>Raleway</option>
                                                <option value="Oswald" {{ old('data.description_font', $data['description_font'] ?? '') == 'Oswald' ? 'selected' : '' }}>Oswald</option>
                                                <option value="Dancing Script" {{ old('data.description_font', $data['description_font'] ?? '') == 'Dancing Script' ? 'selected' : '' }}>Dancing Script (cursiva)</option>
                                                <option value="Pacifico" {{ old('data.description_font', $data['description_font'] ?? '') == 'Pacifico' ? 'selected' : '' }}>Pacifico (cursiva)</option>
                                                <option value="Great Vibes" {{ old('data.description_font', $data['description_font'] ?? '') == 'Great Vibes' ? 'selected' : '' }}>Great Vibes (cursiva)</option>
                                                <option value="Caveat" {{ old('data.description_font', $data['description_font'] ?? '') == 'Caveat' ? 'selected' : '' }}>Caveat (manuscrita)</option>
                                                <option value="Satisfy" {{ old('data.description_font', $data['description_font'] ?? '') == 'Satisfy' ? 'selected' : '' }}>Satisfy (cursiva)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Estilo</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="checkbox" class="btn-check" name="data[description_italic]" id="description_italic" value="1" {{ old('data.description_italic', $data['description_italic'] ?? '') ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                                <label class="btn btn-outline-secondary btn-sm" for="description_italic" title="Cursiva"><i class="bi bi-type-italic"></i></label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small">Tipografía caption</label>
                                            <select class="form-select form-select-sm" name="data[caption_font]" id="caption_font" {{ $isReadOnly ? 'disabled' : '' }}>
                                                <option value="">Por defecto</option>
                                                <option value="Inter" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                                <option value="Roboto" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                                <option value="Open Sans" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                                <option value="Lato" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Lato' ? 'selected' : '' }}>Lato</option>
                                                <option value="Montserrat" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                                <option value="Poppins" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Poppins' ? 'selected' : '' }}>Poppins</option>
                                                <option value="Playfair Display" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                                <option value="Merriweather" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Merriweather' ? 'selected' : '' }}>Merriweather</option>
                                                <option value="Lora" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Lora' ? 'selected' : '' }}>Lora</option>
                                                <option value="Source Serif Pro" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Source Serif Pro' ? 'selected' : '' }}>Source Serif Pro</option>
                                                <option value="Raleway" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Raleway' ? 'selected' : '' }}>Raleway</option>
                                                <option value="Oswald" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Oswald' ? 'selected' : '' }}>Oswald</option>
                                                <option value="Dancing Script" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Dancing Script' ? 'selected' : '' }}>Dancing Script (cursiva)</option>
                                                <option value="Pacifico" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Pacifico' ? 'selected' : '' }}>Pacifico (cursiva)</option>
                                                <option value="Great Vibes" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Great Vibes' ? 'selected' : '' }}>Great Vibes (cursiva)</option>
                                                <option value="Caveat" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Caveat' ? 'selected' : '' }}>Caveat (manuscrita)</option>
                                                <option value="Satisfy" {{ old('data.caption_font', $data['caption_font'] ?? '') == 'Satisfy' ? 'selected' : '' }}>Satisfy (cursiva)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Estilo</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="checkbox" class="btn-check" name="data[caption_italic]" id="caption_italic" value="1" {{ old('data.caption_italic', $data['caption_italic'] ?? '') ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                                <label class="btn btn-outline-secondary btn-sm" for="caption_italic" title="Cursiva"><i class="bi bi-type-italic"></i></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 mt-3">
                                    <label class="form-label">{{ __element('hero.image_url') }}</label>
                                    @if(!$isReadOnly)
                                        {{-- Current image preview --}}
                                        @if(old('data.image_url', $data['image_url'] ?? ''))
                                        <div class="mb-2">
                                            <img id="hero_image_preview_edit" src="{{ old('data.image_url', $data['image_url'] ?? '') }}" class="img-thumbnail" style="max-height: 150px; max-width: 300px; object-fit: cover;">
                                        </div>
                                        @else
                                        <div class="mb-2">
                                            <img id="hero_image_preview_edit" src="" class="img-thumbnail" style="max-height: 150px; max-width: 300px; object-fit: cover; display: none;">
                                        </div>
                                        @endif

                                        <div class="mb-3">
                                            <label class="form-label">{{ __element('hero.image_alt') }}</label>
                                            <input type="text" class="form-control" name="data[image_alt]" value="{{ old('data.image_alt', $data['image_alt'] ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                        </div>

                                        {{-- File upload input --}}
                                        <div class="mb-2">
                                            <input type="file"
                                                   class="form-control"
                                                   id="hero_image_file"
                                                   accept="image/jpeg,image/png,image/gif,image/webp">
                                            <small class="text-muted">Formatos: JPG, PNG, GIF, WEBP. Máx 10MB.</small>
                                        </div>

                                        {{-- Hidden field for URL --}}
                                        <input type="hidden" id="hero_image_url_edit" name="data[image_url]" value="{{ old('data.image_url', $data['image_url'] ?? '') }}">

                                        {{-- Upload progress --}}
                                        <div id="hero_upload_progress" class="progress mb-2" style="height: 5px; display: none;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div id="hero_upload_status" class="small text-muted"></div>

                                        {{-- Manual URL option (collapsible) --}}
                                        <div class="mt-2">
                                            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#manualUrlCollapse" role="button" aria-expanded="false">
                                                <i class="bi bi-link-45deg"></i> O introducir URL manualmente
                                            </a>
                                            <div class="collapse mt-2" id="manualUrlCollapse">
                                                <input type="text" class="form-control form-control-sm" id="hero_image_url_manual" placeholder="https://ejemplo.com/imagen.jpg" value="{{ old('data.image_url', $data['image_url'] ?? '') }}">
                                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="applyManualUrl">Aplicar URL</button>
                                            </div>
                                        </div>
                                    @else
                                        <input type="text" class="form-control" name="data[image_url]" value="{{ old('data.image_url', $data['image_url'] ?? '') }}" disabled>
                                        @if(old('data.image_url', $data['image_url'] ?? ''))
                                            <img src="{{ old('data.image_url', $data['image_url'] ?? '') }}" class="img-fluid rounded border mt-2" style="max-height: 150px;">
                                        @endif
                                        <div class="mb-3 mt-3">
                                            <label class="form-label">{{ __element('hero.image_alt') }}</label>
                                            <input type="text" class="form-control" name="data[image_alt]" value="{{ old('data.image_alt', $data['image_alt'] ?? '') }}" disabled>
                                        </div>
                                    @endif
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
                            <div class="mb-3">
                                <label for="style_preset" class="form-label">{{ __element('element.style_preset') }}</label>
                                <select class="form-select" id="style_preset" name="settings[style_preset]" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="">{{ __element('element.preset_default') }}</option>
                                    <option value="default" {{ old('settings.style_preset', $element->getSettings()['style_preset'] ?? '') === 'default' ? 'selected' : '' }}>Estilo Default</option>
                                </select>
                                <small class="form-text text-muted">{{ __element('element.style_preset_help') }}</small>
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

// ============================================
// IMAGE UPLOAD FUNCTIONALITY
// ============================================
const heroImageFile = document.getElementById('hero_image_file');
const heroImageUrlEdit = document.getElementById('hero_image_url_edit');
const heroImagePreview = document.getElementById('hero_image_preview_edit');
const heroUploadProgress = document.getElementById('hero_upload_progress');
const heroUploadStatus = document.getElementById('hero_upload_status');
const applyManualUrl = document.getElementById('applyManualUrl');
const heroImageUrlManual = document.getElementById('hero_image_url_manual');

if (heroImageFile) {
    heroImageFile.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Tipo de archivo no válido',
                text: 'Solo se permiten: JPG, PNG, GIF, WEBP'
            });
            this.value = '';
            return;
        }

        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Archivo demasiado grande',
                text: 'El tamaño máximo es 10MB'
            });
            this.value = '';
            return;
        }

        // Upload the file
        const formData = new FormData();
        formData.append('image', file);
        formData.append('_token', csrfToken);

        // Show progress
        heroUploadProgress.style.display = 'block';
        heroUploadStatus.textContent = 'Subiendo imagen...';
        heroUploadProgress.querySelector('.progress-bar').style.width = '0%';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '{{ route("tenant.elements.upload-image") }}', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                heroUploadProgress.querySelector('.progress-bar').style.width = percent + '%';
            }
        };

        xhr.onload = function() {
            heroUploadProgress.style.display = 'none';

            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Update hidden field and preview
                    heroImageUrlEdit.value = response.url;
                    heroImagePreview.src = response.url;
                    heroImagePreview.style.display = 'block';
                    heroUploadStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i> Imagen subida correctamente';

                    // Update manual URL field if visible
                    if (heroImageUrlManual) {
                        heroImageUrlManual.value = response.url;
                    }
                } else {
                    heroUploadStatus.innerHTML = '<i class="bi bi-x-circle text-danger"></i> ' + (response.message || 'Error al subir la imagen');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al subir la imagen'
                    });
                }
            } catch (e) {
                heroUploadStatus.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Error al procesar la respuesta';
            }

            // Clear file input
            heroImageFile.value = '';
        };

        xhr.onerror = function() {
            heroUploadProgress.style.display = 'none';
            heroUploadStatus.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Error de conexión';
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor'
            });
        };

        xhr.send(formData);
    });
}

// Manual URL application
if (applyManualUrl && heroImageUrlManual) {
    applyManualUrl.addEventListener('click', function() {
        const url = heroImageUrlManual.value.trim();
        if (url) {
            heroImageUrlEdit.value = url;
            heroImagePreview.src = url;
            heroImagePreview.style.display = 'block';
            heroUploadStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i> URL aplicada';

            // Verify the image loads
            heroImagePreview.onerror = function() {
                heroUploadStatus.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i> La imagen no se pudo cargar, verifica la URL';
            };
        }
    });
}

// Reset colors to default
const resetColorsBtn = document.getElementById('resetColorsBtn');
if (resetColorsBtn) {
    resetColorsBtn.addEventListener('click', function() {
        const defaultColors = {
            'subheading_color': '#64748b',
            'heading_color': '#0f172a',
            'description_color': '#475569',
            'card_bg_color': '#ffffff',
            'card_wrapper_bg_color': '#e2e8f0',
            'caption_color': '#0f172a'
        };

        Object.keys(defaultColors).forEach(function(id) {
            const input = document.getElementById(id);
            if (input) {
                input.value = defaultColors[id];
            }
        });

        Swal.fire({
            icon: 'success',
            title: 'Colores restaurados',
            text: 'Se han restaurado los colores por defecto',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
    });
}

// Reset fonts to default
const resetFontsBtn = document.getElementById('resetFontsBtn');
if (resetFontsBtn) {
    resetFontsBtn.addEventListener('click', function() {
        // Reset font selects
        ['subheading_font', 'heading_font', 'description_font', 'caption_font'].forEach(function(id) {
            const select = document.getElementById(id);
            if (select) {
                select.value = '';
            }
        });

        // Reset italic checkboxes
        ['subheading_italic', 'heading_italic', 'description_italic', 'caption_italic'].forEach(function(id) {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                checkbox.checked = false;
            }
        });

        Swal.fire({
            icon: 'success',
            title: 'Tipografías restauradas',
            text: 'Se han restaurado las tipografías por defecto',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
    });
}
</script>
@endpush
@endsection
