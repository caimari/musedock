@php
    // Función helper para limpiar entidades HTML de los mensajes flash
    function cleanFlashMessage($message) {
        if (!$message) return null;
        // Decodificar entidades HTML (&quot;, &#039;, etc.)
        $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
        // Escapar para JavaScript pero mantener comillas legibles
        $message = addslashes($message);
        return $message;
    }

    $success = cleanFlashMessage(consume_flash('success'));
    $error = cleanFlashMessage(consume_flash('error'));
    $warning = cleanFlashMessage(consume_flash('warning'));
    $info = cleanFlashMessage(consume_flash('info'));
@endphp

@if ($success)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{!! $success !!}',
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
                text: '{!! $error !!}',
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
                text: '{!! $warning !!}',
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
                text: '{!! $info !!}',
                confirmButtonText: 'Aceptar'
            });
        });
    </script>
@endif
