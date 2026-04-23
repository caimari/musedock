@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
.csp-textarea {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.875rem;
    line-height: 1.6;
}
.csp-textarea::placeholder {
    color: #adb5bd !important;
    opacity: 0.7 !important;
    font-style: italic;
}
.csp-preview {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.8rem;
    background: #1e1e2e;
    color: #a6e3a1;
    padding: 1rem;
    border-radius: 0.375rem;
    word-break: break-all;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
}
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-shield-lock me-2"></i>{{ $title }}</h2>
                <p class="text-muted mb-0">{{ __('security_settings_subtitle') }}</p>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <form method="POST" action="/{{ admin_path() }}/settings/security" id="cspForm">
            {!! csrf_field() !!}

            <div class="row">
                <div class="col-lg-8">
                    {{-- Info card --}}
                    <div class="alert alert-info d-flex align-items-start mb-4">
                        <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                        <div>
                            <strong>Content Security Policy (CSP)</strong><br>
                            {{ __('csp_info_text') }}
                        </div>
                    </div>

                    {{-- connect-src --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-plug me-2"></i>{{ __('csp_connect_src_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ __('csp_connect_src_help') }}</p>
                            <textarea name="csp_connect_src" class="form-control csp-textarea" rows="4"
                                      placeholder="wss://example.com&#10;https://api.example.com&#10;https://*.myservice.com"
                                      >{{ $settings['csp_connect_src'] ?? '' }}</textarea>
                            <small class="text-muted">
                                {{ __('csp_one_per_line') }}
                                — {{ __('csp_accepted_schemes') }}: <code>https://</code>, <code>wss://</code>
                            </small>
                        </div>
                    </div>

                    {{-- script-src --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-code-slash me-2"></i>{{ __('csp_script_src_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ __('csp_script_src_help') }}</p>
                            <textarea name="csp_script_src" class="form-control csp-textarea" rows="4"
                                      placeholder="https://cdn.example.com&#10;https://widgets.example.com"
                                      >{{ $settings['csp_script_src'] ?? '' }}</textarea>
                            <small class="text-muted">
                                {{ __('csp_one_per_line') }}
                                — {{ __('csp_accepted_schemes') }}: <code>https://</code>
                            </small>
                        </div>
                    </div>

                    {{-- frame-src --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-window-stack me-2"></i>{{ __('csp_frame_src_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ __('csp_frame_src_help') }}</p>
                            <textarea name="csp_frame_src" class="form-control csp-textarea" rows="3"
                                      placeholder="https://calendly.com&#10;https://player.vimeo.com"
                                      >{{ $settings['csp_frame_src'] ?? '' }}</textarea>
                            <small class="text-muted">
                                {{ __('csp_one_per_line') }}
                                — {{ __('csp_accepted_schemes') }}: <code>https://</code>
                            </small>
                        </div>
                    </div>

                    {{-- img-src --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-image me-2"></i>{{ __('csp_img_src_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ __('csp_img_src_help') }}</p>
                            <textarea name="csp_img_src" class="form-control csp-textarea" rows="3"
                                      placeholder="https://images.example.com&#10;https://cdn.myservice.com"
                                      >{{ $settings['csp_img_src'] ?? '' }}</textarea>
                            <small class="text-muted">
                                {{ __('csp_one_per_line') }}
                                — {{ __('csp_accepted_schemes') }}: <code>https://</code>
                            </small>
                        </div>
                    </div>

                    {{-- Save --}}
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> {{ __('common.save') }}
                        </button>
                        <a href="/{{ admin_path() }}/settings" class="btn btn-outline-secondary ms-2">
                            {{ __('common.cancel') }}
                        </a>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-lg-4">
                    {{-- Preview --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-eye me-2"></i>{{ __('csp_preview_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ __('csp_preview_help') }}</p>
                            <div class="csp-preview" id="cspPreview">{{ __('csp_preview_empty') }}</div>
                        </div>
                    </div>

                    {{-- Common examples --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>{{ __('csp_examples_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong class="small">Chatbots (Intercom, Tidio, Crisp)</strong>
                                <div class="small text-muted">
                                    connect-src: <code>wss://widget.example.com</code><br>
                                    script-src: <code>https://widget.example.com</code>
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong class="small">Analytics (Plausible, Fathom)</strong>
                                <div class="small text-muted">
                                    connect-src: <code>https://plausible.io</code><br>
                                    script-src: <code>https://plausible.io</code>
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong class="small">{{ __('csp_example_forms') }}</strong>
                                <div class="small text-muted">
                                    frame-src: <code>https://calendly.com</code><br>
                                    frame-src: <code>https://form.typeform.com</code>
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong class="small">Stripe</strong>
                                <div class="small text-muted">
                                    connect-src: <code>https://api.stripe.com</code><br>
                                    script-src: <code>https://js.stripe.com</code><br>
                                    frame-src: <code>https://js.stripe.com</code>
                                </div>
                            </div>
                            <div>
                                <strong class="small">{{ __('csp_example_video') }}</strong>
                                <div class="small text-muted">
                                    frame-src: <code>https://fast.wistia.net</code><br>
                                    script-src: <code>https://fast.wistia.com</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Security warning --}}
                    <div class="card border-warning">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0 text-dark"><i class="bi bi-exclamation-triangle me-2"></i>{{ __('csp_warning_title') }}</h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-0">{{ __('csp_warning_text') }}</p>
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
document.addEventListener('DOMContentLoaded', function() {
    const fields = {
        connect_src: document.querySelector('[name="csp_connect_src"]'),
        script_src: document.querySelector('[name="csp_script_src"]'),
        frame_src: document.querySelector('[name="csp_frame_src"]'),
        img_src: document.querySelector('[name="csp_img_src"]'),
    };
    const preview = document.getElementById('cspPreview');

    function updatePreview() {
        let parts = [];
        for (const [directive, el] of Object.entries(fields)) {
            const lines = el.value.trim().split(/\n/).filter(l => l.trim());
            if (lines.length > 0) {
                const name = directive.replace('_', '-');
                parts.push(name + ': ' + lines.map(l => l.trim()).join(' '));
            }
        }
        preview.textContent = parts.length > 0
            ? '{{ __("tenant.csp_preview_additions") }}:\n\n' + parts.join(';\n')
            : '{{ __("tenant.csp_preview_empty") }}';
    }

    for (const el of Object.values(fields)) {
        el.addEventListener('input', updatePreview);
    }
    updatePreview();
});
</script>
@endpush
