{{--
    Vista superadmin del Social Publisher.
    Reutilizamos 100% la vista del tenant (misma UI: acordeones, modales
    SweetAlert2, etc.). Al renderizarse desde el path "superadmin.instagram.index",
    renderModule detecta el contexto superadmin y carga el layout correcto
    (sidebar del superadmin, no el del tenant).
    El controller Superadmin pasa $basePath='musedock' y $tenantId=null.
--}}
@include('tenant.instagram.index')
