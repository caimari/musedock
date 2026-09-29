@extends('layouts.login-tabler')

@section('title', $title)

@section('content')
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="text-center mb-4 mt-4">
      <img src="/assets/tenant/img/logo.png" class="logo-login" style="height: 50px; width: auto;" alt="MuseDock" />
    </div>

    <div class="card card-md">
      <div class="card-body">
        <h2 class="h2 text-center mb-2">Verificación en dos pasos</h2>
        <p class="text-muted text-center mb-4" id="hintTotp">Introduce el código de 6 dígitos de tu app de autenticación.</p>
        <p class="text-muted text-center mb-4 d-none" id="hintRecovery">Introduce uno de tus códigos de recuperación. Cada código solo sirve una vez.</p>

        @php
          $error = consume_flash('error');
        @endphp
        @if($error)
          <div class="alert alert-danger" role="alert">{{ $error }}</div>
        @endif

        <form method="POST" action="/{{ admin_path() }}/login/2fa" autocomplete="off">
          {!! csrf_field() !!}
          <input type="hidden" name="use_recovery" id="useRecovery" value="0">
          <div class="mb-3">
            <input type="text" name="code" id="code" class="form-control form-control-lg text-center" style="letter-spacing:0.3em;font-family:monospace;"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary w-100">Verificar</button>
        </form>

        <div class="text-center mt-3">
          <a href="#" id="toggleRecovery" class="small">¿No tienes el móvil? Usa un código de recuperación</a>
        </div>
      </div>
    </div>

    <div class="text-center text-muted mt-3">
      <a href="/{{ admin_path() }}/login">Volver al inicio de sesión</a>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var toggle = document.getElementById('toggleRecovery');
  var input = document.getElementById('code');
  var flag = document.getElementById('useRecovery');
  toggle.addEventListener('click', function (e) {
    e.preventDefault();
    var recovery = flag.value !== '1';
    flag.value = recovery ? '1' : '0';
    input.value = '';
    input.maxLength = recovery ? 20 : 6;
    input.inputMode = recovery ? 'text' : 'numeric';
    input.pattern = recovery ? '[A-Za-z0-9-]+' : '[0-9]*';
    input.placeholder = recovery ? 'XXXX-XXXX' : '000000';
    input.autocomplete = recovery ? 'off' : 'one-time-code';
    document.getElementById('hintTotp').classList.toggle('d-none', recovery);
    document.getElementById('hintRecovery').classList.toggle('d-none', !recovery);
    toggle.textContent = recovery ? 'Usar el código de la app' : '¿No tienes el móvil? Usa un código de recuperación';
    input.focus();
  });
})();
</script>
@endpush
