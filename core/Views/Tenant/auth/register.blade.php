@extends('layouts.login-tabler')

@section('title', __('register_title') ?? 'Crear cuenta')

@section('content')
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="text-center mb-4">
      <a href="#" onclick="window.location.href = window.location.origin" class="navbar-brand navbar-brand-autodark">
       <img src="/assets/tenant/img/logo.png" class="logo-login" style="height: 50px; width: auto;" alt="MuseDock" />
      </a>
    </div>

    <form class="card card-md" method="POST" action="/{{ admin_path() }}/register" autocomplete="off">
      {!! csrf_field() !!}
      <div class="card-body">
        <h2 class="card-title text-center mb-4">{{ __('register_title') ?? 'Crear cuenta' }}</h2>

        {{-- Mensajes Flash --}}
        @if($error = consume_flash('error'))
          <div class="alert alert-danger">{{ $error }}</div>
        @endif
        @if($success = consume_flash('success'))
          <div class="alert alert-success">{{ $success }}</div>
        @endif

        <div class="mb-3">
          <label class="form-label">{{ __('name') ?? 'Nombre completo' }}</label>
          <input type="text" name="name" class="form-control" placeholder="Nombre y apellidos" value="{{ old('name') }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('email') ?? 'Correo electrónico' }}</label>
          <input type="email" name="email" class="form-control" placeholder="email@ejemplo.com" value="{{ old('email') }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('password') ?? 'Contraseña' }}</label>
          <div class="input-group input-group-flat">
            <input type="password" name="password" class="form-control" placeholder="********" autocomplete="off" required>
            <span class="input-group-text">
              <a href="#" class="link-secondary" title="Mostrar contraseña" data-bs-toggle="tooltip">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                  <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                </svg>
              </a>
            </span>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('password_confirmation') ?? 'Confirmar contraseña' }}</label>
          <input type="password" name="password_confirmation" class="form-control" placeholder="********" required>
        </div>

        <div class="mb-3">
          <label class="form-check">
            <input type="checkbox" name="terms" class="form-check-input" required />
            <span class="form-check-label">
              Acepto los <a href="/terminos-y-condiciones" tabindex="-1">términos y condiciones</a>.
            </span>
          </label>
        </div>

        <div class="form-footer">
          <button type="submit" class="btn btn-primary w-100">
            {{ __('register_button') ?? 'Crear cuenta' }}
          </button>
        </div>
      </div>
    </form>

    <div class="hr-text">o</div>

    <div class="card-body">
      <div class="row">
        <div class="col">
          <a href="#" class="btn btn-4 w-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon text-danger icon-2" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21.35 11.1H12v2.9h5.3c-.3 1.7-1.8 3.4-5.3 3.4-3.2 0-5.8-2.7-5.8-6s2.6-6 5.8-6c1.8 0 3 .8 3.7 1.4l2.5-2.4C17.6 3.1 15.2 2 12 2 6.8 2 2.7 6 2.7 11s4.1 9 9.3 9c5.4 0 9-3.7 9-8.9 0-.6-.1-1.2-.2-1.6z"/>
            </svg>
            Registrarse con Google
          </a>
        </div>
        <div class="col">
          <a href="#" class="btn btn-4 w-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon text-x icon-2" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none">
              <path d="M4 4l11.733 16h4.267l-11.733 -16z" />
              <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772" />
            </svg>
            Registrarse con X
          </a>
        </div>
      </div>
    </div>

    <div class="text-center text-secondary mt-3">
      ¿Ya tienes cuenta? <a href="/{{ admin_path() }}/login" tabindex="-1">Inicia sesión</a>
    </div>
  </div>
</div>
@endsection
