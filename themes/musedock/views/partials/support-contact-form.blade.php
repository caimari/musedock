@php
  $__supportSuccessMessage = flash('success');
  $__supportErrorMessage = flash('error');
  $__supportPrivacyUrl = function_exists('legal_page_url')
    ? legal_page_url(['privacy', 'privacidad', 'politica-de-privacidad', 'politica-privacidad'], 'privacy')
    : '/p/privacy';
@endphp

<style>
  .ziph-support-contact-wrap {
    margin-top: 28px;
    border-top: 1px solid #e6e9f0;
    padding-top: 24px;
  }

  .ziph-support-contact-wrap .ziph-support-contact-title {
    margin: 0 0 8px;
    font-size: 26px;
    font-weight: 700;
    color: #2f3b4a;
  }

  .ziph-support-contact-wrap .ziph-support-contact-subtitle {
    margin: 0 0 16px;
    color: #4b5a6d;
    font-size: 15px;
    line-height: 1.6;
  }

  .ziph-support-contact-wrap .ziph-support-contact-card {
    background: #ffffff;
    padding: 20px;
    border: 1px solid #e6e9f0;
    border-radius: 10px;
  }

  .ziph-support-contact-wrap .wpcf7-form-control:not(.wpcf7-submit) {
    border: 1px solid #d2d8e4 !important;
    background: #ffffff !important;
    color: #111827 !important;
    box-shadow: none !important;
  }

  .ziph-support-contact-wrap .wpcf7-form-control::placeholder {
    color: #515b6e !important;
    font-weight: 700 !important;
    opacity: 1 !important;
  }

  .ziph-support-contact-wrap .wpcf7-form-control:not(.wpcf7-submit):focus {
    border-color: #6da8f6 !important;
    box-shadow: 0 0 0 2px rgba(84, 157, 245, .14) !important;
  }

  .ziph-support-contact-wrap .ziph-support-consent-label,
  .ziph-support-contact-wrap .ziph-support-consent-label span {
    color: #111827 !important;
    font-weight: 600 !important;
  }

  .ziph-support-contact-wrap .ziph-support-privacy-link {
    color: #2f7bd9 !important;
    text-decoration: underline;
    text-underline-offset: 2px;
  }

  .ziph-support-contact-wrap .ziph-support-contact-btn {
    border: 1px solid #2f7bd9 !important;
    background: #2f7bd9 !important;
    color: #ffffff !important;
    font-weight: 600;
  }
</style>

<div class="ziph-support-contact-wrap">
  <h2 class="ziph-support-contact-title">Contacto</h2>
  <p class="ziph-support-contact-subtitle">Si prefieres, también puedes escribirnos desde aquí.</p>

  @if($__supportSuccessMessage)
    <div class="alert alert-success" style="margin-bottom:16px;">{{ $__supportSuccessMessage }}</div>
  @endif
  @if($__supportErrorMessage)
    <div class="alert alert-danger" style="margin-bottom:16px;">{{ $__supportErrorMessage }}</div>
  @endif

  <div class="ziph-footer_conform ziph-support-contact-card">
    <form action="{{ url('/contact') }}" method="POST" class="wpcf7-form">
      {!! csrf_field() !!}
      <input type="hidden" name="_page_url" value="{{ $_SERVER['REQUEST_URI'] ?? '/soporte' }}">

      <div class="row ziph-input_group ziph-m-0">
        <div class="col-md-6">
          <input class="wpcf7-form-control wpcf7-text" placeholder="{{ __('footer.name') }}" type="text" name="name" value="{{ old('name', '') }}" required>
        </div>
        <div class="col-md-6">
          <input class="wpcf7-form-control wpcf7-email wpcf7-text" placeholder="{{ __('footer.email') }}" type="email" name="email" value="{{ old('email', '') }}" required>
        </div>
      </div>

      <div class="ziph-input_single ziph-m-0" style="margin-top:10px;">
        <textarea class="wpcf7-form-control wpcf7-textarea" placeholder="{{ __('footer.message') }}" name="message" rows="6" required>{{ old('message', '') }}</textarea>
      </div>

      <div class="ziph-input_single ziph-m-0" style="margin-top:10px;">
        <label class="ziph-support-consent-label" style="display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.4;">
          <input type="checkbox" name="legal_consent" value="1" required style="width:16px;height:16px;min-width:16px;margin-top:2px;accent-color:#2f7bd9;">
          <span>Acepto la <a class="ziph-support-privacy-link" href="{{ $__supportPrivacyUrl }}" target="_blank" rel="noopener noreferrer">Política de Privacidad</a>.</span>
        </label>
      </div>

      <p style="margin:14px 0 0;">
        <input class="wpcf7-form-control wpcf7-submit has-spinner ziph-submit-btn ziph-support-contact-btn" type="submit" value="{{ __('footer.contact_now') }}">
      </p>
    </form>
  </div>
</div>
