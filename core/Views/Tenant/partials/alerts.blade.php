@if($msg = consume_flash('success'))
  <div class="alert alert-success alert-dismissible" role="alert">
    <div class="d-flex">
      <div>
        <!-- Icono de éxito -->
        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24"
             viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
             stroke-linecap="round" stroke-linejoin="round">
          <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
          <path d="M5 12l5 5l10 -10" />
        </svg>
      </div>
      <div>{{ $msg }}</div>
    </div>
    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
  </div>
@endif

@if($msg = consume_flash('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    <div class="d-flex">
      <div>
        <!-- Icono de error -->
        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24"
             viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
             stroke-linecap="round" stroke-linejoin="round">
          <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
          <path d="M12 9v2m0 4v.01" />
          <path d="M12 5a7 7 0 1 0 0 14a7 7 0 0 0 0 -14" />
        </svg>
      </div>
      <div>{{ $msg }}</div>
    </div>
    <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
  </div>
@endif
