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
@endphp

@if (session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '{!! cleanFlashMessage(session('success')) !!}',
            confirmButtonText: 'Aceptar'
        });
    </script>
@endif

@if (session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{!! cleanFlashMessage(session('error')) !!}',
            confirmButtonText: 'Aceptar'
        });
    </script>
@endif

@if (session('warning'))
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: '{!! cleanFlashMessage(session('warning')) !!}',
            confirmButtonText: 'Aceptar'
        });
    </script>
@endif
