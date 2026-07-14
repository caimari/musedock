@extends('layouts.app')

@section('title', 'Contacto')

@section('content')
@php
  $successMessage = flash('success');
  $errorMessage = flash('error');
  $__privacyUrl = function_exists('legal_page_url')
    ? legal_page_url(['privacy', 'privacidad', 'politica-de-privacidad', 'politica-privacidad'], 'privacy')
    : '/p/privacy';
@endphp

<section class="about-low-area section-padding40">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="section-tittle text-center mb-55">
          <h2>Contacto</h2>
          <p>Escríbenos y te responderemos lo antes posible.</p>
        </div>

        @if($successMessage)
          <div class="alert alert-success">{{ $successMessage }}</div>
        @endif
        @if($errorMessage)
          <div class="alert alert-danger">{{ $errorMessage }}</div>
        @endif

        <form action="{{ url('/contact') }}" method="POST">
          {!! csrf_field() !!}
          <input type="hidden" name="_page_url" value="{{ $_SERVER['REQUEST_URI'] ?? '/contact' }}">

          <div class="form-group">
            <input class="form-control" type="text" name="name" value="{{ old('name', '') }}" placeholder="{{ __('footer.name') }}" required>
          </div>
          <div class="form-group">
            <input class="form-control" type="email" name="email" value="{{ old('email', '') }}" placeholder="{{ __('footer.email') }}" required>
          </div>
          <div class="form-group">
            <textarea class="form-control" name="message" rows="6" placeholder="{{ __('footer.message') }}" required>{{ old('message', '') }}</textarea>
          </div>

          <div class="form-group" style="margin-top:10px;">
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.4;">
              <input type="checkbox" name="legal_consent" value="1" required style="width:16px;height:16px;min-width:16px;margin-top:2px;">
              <span>Acepto la <a href="{{ $__privacyUrl }}" target="_blank" rel="noopener noreferrer">Política de Privacidad</a>.</span>
            </label>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <button class="btn btn-primary" type="submit">{{ __('footer.contact_now') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
