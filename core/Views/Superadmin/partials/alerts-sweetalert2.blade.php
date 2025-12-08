@php
    $success = consume_flash('success');
    $error = consume_flash('error');
    $warning = consume_flash('warning');
    $info = consume_flash('info');
@endphp

@if ($success)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{{ $success }}',
                confirmButtonText: 'Aceptar',
                timer: 3000,
                timerProgressBar: true
            });
        });
    </script>
@endif

@if ($error)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ $error }}',
                confirmButtonText: 'Aceptar'
            });
        });
    </script>
@endif

@if ($warning)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: '{{ $warning }}',
                confirmButtonText: 'Aceptar'
            });
        });
    </script>
@endif

@if ($info)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'info',
                title: 'Información',
                text: '{{ $info }}',
                confirmButtonText: 'Aceptar'
            });
        });
    </script>
@endif
