@extends('layouts.app')

@section('title')
{{ 'Contacto | ' . ($siteName ?? site_setting('site_name', 'MuseDock')) }}
@endsection

@section('description')
{{ 'Ponte en contacto con nuestro equipo para soporte, dudas comerciales o colaboración.' }}
@endsection

@section('content')
@php
  $successMessage = flash('success');
  $errorMessage = flash('error');
  $__privacyUrl = function_exists('legal_page_url')
    ? legal_page_url(['privacy', 'privacidad', 'politica-de-privacidad', 'politica-privacidad'], 'privacy')
    : '/p/privacy';
@endphp

@push('styles')
<style>
  .ziph-page_content .ziph-contact-title {
    color: #2f3b4a !important;
    font-size: 34px !important;
  }

  .ziph-contact-subtitle {
    color: #4b5a6d !important;
    font-size: 15px;
    line-height: 1.65;
    font-weight: 600;
    margin: 0 0 24px;
  }

  .ziph-page_content .ziph-contact-meta {
    color: #111827 !important;
    font-size: 15px;
    line-height: 1.8;
  }

  .ziph-page_content .ziph-contact-meta a {
    color: #111827 !important;
  }

  .ziph-contact-card {
    background: #ffffff;
    padding: 24px;
    border: 1px solid #e6e9f0;
    border-radius: 10px;
    box-shadow: 0 12px 26px rgba(10, 18, 38, 0.12);
  }

  .ziph-page_content .ziph-contact-card input.wpcf7-form-control:not(.wpcf7-submit),
  .ziph-page_content .ziph-contact-card textarea.wpcf7-form-control:not(.wpcf7-submit) {
    border: 1px solid #d2d8e4 !important;
    background: #ffffff !important;
    color: #111827 !important;
    box-shadow: none !important;
  }

  .ziph-page_content .ziph-contact-card .wpcf7-form-control::placeholder {
    color: #515b6e !important;
    font-weight: 700 !important;
    opacity: 1 !important;
  }

  .ziph-page_content .ziph-contact-card .wpcf7-form-control::-webkit-input-placeholder {
    color: #515b6e !important;
    font-weight: 700 !important;
    opacity: 1 !important;
  }

  .ziph-page_content .ziph-contact-card .wpcf7-form-control::-moz-placeholder {
    color: #515b6e !important;
    font-weight: 700 !important;
    opacity: 1 !important;
  }

  .ziph-page_content .ziph-contact-card .wpcf7-form-control:not(.wpcf7-submit):focus {
    border-color: #6da8f6 !important;
    box-shadow: 0 0 0 2px rgba(84, 157, 245, .14) !important;
  }

  .ziph-contact-card .ziph-privacy-link {
    color: #2f7bd9 !important;
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
  }

  .ziph-page_content .ziph-contact-card .ziph-consent-label,
  .ziph-page_content .ziph-contact-card .ziph-consent-label span {
    color: #111827 !important;
    font-weight: 600 !important;
  }

  .ziph-contact-card .ziph-contact-btn {
    border: 1px solid #2f7bd9 !important;
    background: #2f7bd9 !important;
    color: #ffffff !important;
    font-weight: 600;
  }
</style>
@endpush

<div class="padding-none ziph-page_content">
  <div class="container" style="padding-top:50px;padding-bottom:60px;">
    <div class="ziph-page_warp">
      <div class="row">
        <div class="col-md-5">
          <h1 class="ziph-contact-title" style="font-size:34px;font-weight:700;line-height:1.1;margin:0 0 12px;">Contacto</h1>
          <p class="ziph-contact-subtitle">Escríbenos y te responderemos lo antes posible.</p>

          <div class="ziph-contact-meta">
            @if(!empty($contactEmail))<p style="margin:0 0 8px;"><i class="fa fa-envelope"></i> {{ $contactEmail }}</p>@endif
            @if(!empty($contactPhone))<p style="margin:0 0 8px;"><i class="fa fa-phone"></i> {{ $contactPhone }}</p>@endif
            @if(!empty($contactAddress))<p style="margin:0;"><i class="fa fa-map-marker"></i> {{ $contactAddress }}</p>@endif
          </div>
        </div>

        <div class="col-md-7">
          @if($successMessage)
            <div class="alert alert-success" style="margin-bottom:16px;">{{ $successMessage }}</div>
          @endif
          @if($errorMessage)
            <div class="alert alert-danger" style="margin-bottom:16px;">{{ $errorMessage }}</div>
          @endif

          <div class="ziph-footer_conform ziph-contact-card">
            <form action="{{ url('/contact') }}" method="POST" class="wpcf7-form">
              {!! csrf_field() !!}
              <input type="hidden" name="_page_url" value="{{ $_SERVER['REQUEST_URI'] ?? '/contact' }}">

              <div class="row ziph-input_group ziph-m-0">
                <div class="col-md-6">
                  <input class="wpcf7-form-control wpcf7-text" placeholder="{{ __('footer.name') }}" type="text" name="name" value="{{ old('name', '') }}" required>
                </div>
                <div class="col-md-6">
                  <input class="wpcf7-form-control wpcf7-email wpcf7-text" placeholder="{{ __('footer.email') }}" type="email" name="email" value="{{ old('email', '') }}" required>
                </div>
              </div>

              <div class="ziph-input_single ziph-m-0" style="margin-top:10px;">
                <textarea class="wpcf7-form-control wpcf7-textarea" placeholder="{{ __('footer.message') }}" name="message" rows="7" required>{{ old('message', '') }}</textarea>
              </div>

              <div class="ziph-input_single ziph-m-0" style="margin-top:10px;">
                <label class="ziph-consent-label" style="display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.4;">
                  <input type="checkbox" name="legal_consent" value="1" required style="width:16px;height:16px;min-width:16px;margin-top:2px;accent-color:#2f7bd9;">
                  <span>Acepto la <a class="ziph-privacy-link" href="{{ $__privacyUrl }}" target="_blank" rel="noopener noreferrer">Política de Privacidad</a>.</span>
                </label>
              </div>

              <p style="margin:14px 0 0;">
                <input class="wpcf7-form-control wpcf7-submit has-spinner ziph-submit-btn ziph-contact-btn" type="submit" value="{{ __('footer.contact_now') }}">
              </p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
