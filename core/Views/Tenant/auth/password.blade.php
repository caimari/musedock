@extends('layouts.login-tabler')

@section('title', __('forgot_password') ?? 'Recuperar contraseña')

@section('content')
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="text-center mb-4">
      <a href="#" onclick="window.location.href = window.location.origin" class="navbar-brand navbar-brand-autodark">
       <img src="/assets/tenant/img/logo.png" class="logo-login" style="height: 50px; width: auto;" alt="MuseDock" />
      </a>
    </div>

    <form class="card card-md" method="POST" action="/{{ admin_path() }}/password/reset" autocomplete="off">
      {!! csrf_field() !!}
      <div class="card-body">
        <h2 class="card-title text-center mb-4">{{ __('forgot_password') ?? 'Recuperar contraseña' }}</h2>

        @if($message = consume_flash('success'))
            <div class="alert alert-success">{{ $message }}</div>
        @endif

        @if($error = consume_flash('error'))
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <p class="text-muted mb-4 text-center">
          Introduce tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
        </p>

        <div class="mb-3">
          <label class="form-label">{{ __('email') ?? 'Correo electrónico' }}</label>
          <input type="email" name="email" class="form-control" placeholder="tucorreo@ejemplo.com" required>
        </div>

        <div class="form-footer">
          <button type="submit" class="btn btn-primary w-100">
            {{ __('send_reset_link') ?? 'Enviar enlace de recuperación' }}
          </button>
        </div>
      </div>
    </form>

    <div class="text-center text-secondary mt-3">
      <a href="/{{ admin_path() }}/login" tabindex="-1">{{ __('back_to_login') ?? 'Volver a iniciar sesión' }}</a>
    </div>
  </div>
</div>
@endsection
