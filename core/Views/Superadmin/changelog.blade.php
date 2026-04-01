@extends('layouts.app')

@section('title', 'Changelog')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-journal-text me-2"></i>Changelog</h2>
                <p class="text-muted mb-0">Historial de cambios de {{ cms_version('name') }}</p>
            </div>
            <span class="badge bg-primary fs-6">v{{ cms_version('version') }}</span>
        </div>

        <!-- v2.8.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.8.0</h5>
                <span class="badge bg-success">Latest</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 1 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Plugin Store</h6>
                <ul class="mb-3">
                    <li><strong>Catalogo de productos:</strong> Nueva pantalla Plugin Store accesible desde Plugins y Modulos. Muestra plugins y modulos premium disponibles con precios, versiones y estado de instalacion</li>
                    <li><strong>Verificacion de licencias:</strong> Sistema de activacion por clave (MDCK-XXXX-XXXX-XXXX) con vinculacion a dominio. Modal SweetAlert2 para introducir y verificar licencias</li>
                    <li><strong>Descarga e instalacion:</strong> Descarga automatica del ZIP desde el License Server, extraccion en la ruta correcta (plugins o modulos) y ejecucion de install.php si existe</li>
                    <li><strong>Acceso dual:</strong> Plugin Store disponible tanto para superadmin (/musedock/plugin-store) como para tenants (/admin/plugin-store)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Soporte de modulos premium</h6>
                <ul class="mb-3">
                    <li><strong>Ruta externa de modulos:</strong> Nueva variable PRIVATE_MODULES_PATH en .env para modulos premium en repo privado separado (musedock-modules)</li>
                    <li><strong>modules_loader.php ampliado:</strong> Escanea modulos en APP_ROOT/modules/ (gratuitos) y PRIVATE_MODULES_PATH/ (premium). Registro de namespaces PSR-4 y carga de archivos en ambas rutas</li>
                    <li><strong>Tres repos independientes:</strong> musedock (publico MIT), musedock-plugins (privado), musedock-modules (privado)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> LicenseClient service</h6>
                <ul class="mb-3">
                    <li><strong>Cliente HTTP:</strong> Servicio LicenseClient para comunicacion con el License Server (getCatalog, verifyLicense, activateLicense, downloadProduct)</li>
                    <li><strong>Almacenamiento local:</strong> Licencias premium almacenadas en storage/premium-licenses.json como cache local</li>
                    <li><strong>Verificacion diaria:</strong> Cron verify-premium-licenses.php que verifica todas las licencias contra el servidor y marca las expiradas</li>
                </ul>
            </div>
        </div>

        <!-- v2.7.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.7.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 31 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> HTML Static Cache (ISR)</h6>
                <ul class="mb-3">
                    <li><strong>Cache hibrido por URL:</strong> Sistema de cache HTML estatico tipo ISR (Incremental Static Regeneration). Las paginas publicas se sirven como archivos .html pre-renderizados, eliminando el bootstrap PHP en cada peticion (~3x mas rapido)</li>
                    <li><strong>Early-exit middleware:</strong> Insercion ultra-temprana en index.php. Si existe cache en disco, se sirve con readfile() + exit antes de cargar sesion, base de datos, modulos o rutas</li>
                    <li><strong>Escritura atomica:</strong> flock() + rename() para evitar race conditions. Dos visitantes simultaneos a la misma URL sin cache no generan conflictos ni archivos corruptos</li>
                    <li><strong>Multi-tenant aislado:</strong> Cache separado por tenant en /storage/html-cache/_tenant_{id}/ con resolucion ligera del tenant desde dominio (sin bootstrap completo)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Invalidacion inteligente</h6>
                <ul class="mb-3">
                    <li><strong>Por eventos:</strong> Al crear/editar/eliminar paginas o posts, se invalida y regenera automaticamente la pagina afectada, el listado del blog y la homepage</li>
                    <li><strong>Por tags:</strong> Sistema de meta-archivos (.meta.json) por pagina cacheada con tags (blog, pages, home, blog-list) para invalidacion selectiva sin escanear todo el disco</li>
                    <li><strong>Cambio de prefijos:</strong> Al modificar blog_url_prefix o page_url_prefix en Settings > Reading, se purga todo el cache del tenant y se regenera con las nuevas URLs</li>
                    <li><strong>Cambio de tema:</strong> Al activar un tema diferente (custom o global), se purga todo el cache del tenant automaticamente</li>
                    <li><strong>Taxonomias:</strong> Al crear/editar/eliminar categorias o tags del blog, se invalidan los listados relacionados</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Exclusiones de seguridad</h6>
                <ul class="mb-3">
                    <li><strong>Sesiones admin:</strong> Visitantes con cookie de sesion PHP nunca reciben cache (siempre contenido dinamico fresco)</li>
                    <li><strong>Rutas protegidas:</strong> /admin, /musedock, /api, /ajax, /media, /storage, /login, /search excluidos permanentemente</li>
                    <li><strong>Solo GET + HTML 200:</strong> Solo se cachean respuestas exitosas (HTTP 200) de tipo text/html en peticiones GET</li>
                    <li><strong>TTL 24h:</strong> Safety net automatico: cache expira tras 24 horas aunque no se invalide manualmente</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> CLI de gestion</h6>
                <ul class="mb-3">
                    <li><strong>cache:warm:</strong> Pre-genera cache de homepage, blog y paginas para todos los tenants o uno especifico (--tenant=ID, --only=home,blog,pages, --limit=N)</li>
                    <li><strong>cache:purge:</strong> Elimina todo el cache o solo el de un tenant especifico</li>
                    <li><strong>cache:status:</strong> Muestra estadisticas: archivos, tamano, antigueedad por tenant</li>
                    <li><strong>cache:enable / cache:disable:</strong> Activa o desactiva el sistema desde .env (HTML_CACHE_ENABLED)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Paginacion con URLs limpias</h6>
                <ul class="mb-3">
                    <li><strong>Rutas /prefix/page/N:</strong> Nueva ruta /{prefix}/page/{num} para paginacion del blog sin query strings (?page=), compatible con el sistema de cache estatico</li>
                </ul>
            </div>
        </div>

        <!-- v2.6.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.6.0</h5>
                <span class="badge bg-secondary">31 Mar 2026</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 31 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Sistema de tipografia del contenido</h6>
                <ul class="mb-3">
                    <li><strong>Panel de apariencia:</strong> Nueva seccion "Tipografia del contenido" con selector de fuentes para titulos (h1-h6) y texto, 3 escalas tipograficas (compacta/normal/grande), colores de texto, titulos y enlaces</li>
                    <li><strong>CSS Variables dinamicas:</strong> 13 nuevas variables CSS (--content-heading-font, --content-body-font, --content-h1-size a h6, --content-text-color, etc.) generadas automaticamente desde el panel</li>
                    <li><strong>TinyMCE WYSIWYG:</strong> content_css dinamico que sincroniza el editor con el frontend: las fuentes, tamanos y colores del tema se reflejan en tiempo real en el editor</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Selector de fuentes en TinyMCE</h6>
                <ul class="mb-3">
                    <li><strong>23 Google Fonts curadas:</strong> Desplegable de fuentes en el editor con Sans-serif, Serif, Monospace y Display. Las fuentes del tenant aparecen primero marcadas con estrella</li>
                    <li><strong>Selector de tamano:</strong> Desplegable de tamanos de fuente (12px a 48px) en la toolbar</li>
                    <li><strong>Carga automatica en frontend:</strong> Las Google Fonts usadas en el contenido se detectan automaticamente y se cargan solo en las paginas que las necesitan (zero overhead en paginas sin fuentes custom)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Mejoras de TinyMCE</h6>
                <ul class="mb-3">
                    <li><strong>Tablas avanzadas:</strong> Boton de tabla en toolbar, propiedades avanzadas (color de fondo, bordes, ancho) por tabla, fila y celda. Tablas nuevas con width 100% por defecto</li>
                    <li><strong>Lightbox configurable:</strong> Toggle en panel de apariencia "Lightbox automatico en imagenes" (on/off). Las imagenes pequenas (<400px) ya no se amplian</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevos layouts de cabecera</h6>
                <ul class="mb-3">
                    <li><strong>Logo + Tabs:</strong> Logo arriba + menu en pestañas (tabs de pagina con pestaña activa)</li>
                    <li><strong>Paper Card:</strong> Header flotante con efecto hojas apiladas, CTA prominente y alineacion de footer</li>
                    <li><strong>Classic:</strong> Menu arriba + logo debajo</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Slug doble barra:</strong> Corregido URL preview en creacion de paginas que mostraba doble barra (//) cuando el prefix estaba vacio</li>
                    <li><strong>Pagina de inicio:</strong> Corregido bug donde marcar una pagina como inicio desde el editor no actualizaba los settings de lectura (is_homepage se perdia en el ORM update)</li>
                    <li><strong>Imagenes centradas:</strong> Corregido centrado de imagenes display:block dentro de parrafos con text-align:center (patron TinyMCE)</li>
                    <li><strong>Tablas responsive:</strong> Tablas del contenido ahora ocupan 100% del ancho con columnas fluidas. Eliminado display:block que rompia el layout. Scroll horizontal en movil</li>
                    <li><strong>Tablas Google Sheets:</strong> Override de col width fijos para que las columnas se distribuyan proporcionalmente</li>
                    <li><strong>Lightbox PNG transparente:</strong> Eliminado fondo gris (#444) en Magnific Popup para imagenes con transparencia</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-hdd-rack me-1"></i> Panel (v1.0.31)</h6>
                <ul class="mb-3">
                    <li><strong>Migracion WordPress:</strong> Verificacion post-migracion de archivos core (index.php, wp-load.php, wp-includes/) con reparacion automatica descargando WP latest</li>
                    <li><strong>Migracion tar:</strong> Logging mejorado de errores de permisos en el tar remoto (Permission denied con conteo y detalle)</li>
                    <li><strong>Creacion de hosting:</strong> Modal de progreso con SSE en tiempo real (7 pasos: usuario, directorios, PHP-FPM, Caddy, permisos, BD). Reconexion automatica si se recarga la pagina</li>
                </ul>
            </div>
        </div>

        <!-- v2.5.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.5.0</h5>
                <span class="badge bg-secondary">29 Mar 2026</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 29 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Arquitectura de temas</h6>
                <ul class="mb-3">
                    <li><strong>Estructura de pagina:</strong> Nueva jerarquia Tema > Estructura > Skin. Selector entre Clasica (cabecera + pie modulares) y Sidebar lateral (autocontenido)</li>
                    <li><strong>Estructura Sidebar:</strong> Opciones propias (idioma, redes, buscador, CTA). Fuerza ancho completo en posts/paginas con nota informativa</li>
                    <li><strong>Dependencias de seccion:</strong> Secciones completas se ocultan/muestran segun estructura elegida</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> AI Skin Generator (nuevo plugin)</h6>
                <ul class="mb-3">
                    <li><strong>Generacion con IA:</strong> Skins completos con descripcion de estilo. Conoce todos los layouts y sus combinaciones</li>
                    <li><strong>Consultor de proyecto:</strong> La IA analiza tu proyecto y recomienda sector, publico y estilo visual</li>
                    <li><strong>Skin aleatorio:</strong> 15 paletas mezclables + layouts al azar, sin consumir tokens</li>
                    <li><strong>Fijar estructura:</strong> 10 plantillas base que fijan layouts y solo varian colores/tipografias</li>
                    <li><strong>Vista previa dinamica:</strong> Refleja cada layout real de header, blog y footer</li>
                    <li><strong>Validacion:</strong> Correccion automatica de contraste, valores invalidos y normalizacion</li>
                    <li><strong>20 prompts de ejemplo</strong> y color pickers nativos</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Gestion de skins y presets</h6>
                <ul class="mb-3">
                    <li><strong>Domain Manager:</strong> Selector rapido de skin + gestion de presets por tenant</li>
                    <li><strong>Temas:</strong> Activar/desactivar y eliminar skins desde el listado</li>
                    <li><strong>Cookie banner:</strong> Colores se adaptan automaticamente al skin aplicado</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Header, Topbar y Blog</h6>
                <ul class="mb-3">
                    <li><strong>Header modular:</strong> Redes sociales, fecha/reloj y buscador como opciones independientes</li>
                    <li><strong>Topbar:</strong> Ticker inline, toggle redes, opciones de reloj reubicadas</li>
                    <li><strong>Ticker:</strong> 7 opciones de color personalizables</li>
                    <li><strong>Newspaper overlay:</strong> Texto sobre imagen en posts destacados</li>
                    <li><strong>Plantilla de post:</strong> Funcional: Sidebar Derecha, Ancho Completo, Sidebar Izquierda</li>
                    <li><strong>Cards categorias:</strong> Nuevo diseno con imagen, contador y hover</li>
                    <li><strong>Footer minimal:</strong> Nuevo layout copyright + enlaces legales</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li>Presets PostgreSQL (ON CONFLICT), select idiomas sin naranja hardcodeado, Cross-Publisher estado publicado, traducciones search, footer boxed minimal, depends_on con valor</li>
                </ul>
            </div>
        </div>

        <!-- v2.4.2 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.4.2</h5>
                <span class="badge bg-secondary">26 Mar 2026</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 26 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>Domain Manager:</strong> Gestion de Cloudflare API Token con interfaz SweetAlert2: verificar permisos, guardar y aplicar token, guia paso a paso</li>
                    <li><strong>Domain Manager:</strong> Card de estado Cloudflare Proxy en edicion de tenant: muestra el estado real (nube naranja/gris) de todos los dominios (principal + alias) consultando la API de CF en tiempo real</li>
                    <li><strong>Domain Manager:</strong> Toggle individual de proxy Cloudflare para cada dominio (principal y alias) con modal de confirmacion</li>
                    <li><strong>Domain Manager:</strong> Estado de proxy Cloudflare en vista de edicion del alias con toggle integrado</li>
                    <li><strong>Domain Manager:</strong> Boton "Recrear ruta Caddy" en la edicion de alias para reparar rutas perdidas tras reinicios de Caddy</li>
                    <li><strong>Domain Manager:</strong> Automatizacion de certificados SSL via DNS-01 para dominios con proxy Cloudflare (nube naranja)</li>
                    <li><strong>Cookies:</strong> 3 variantes de diseño del banner de cookies (tarjeta flotante, barra inferior, modal centrado) con selector visual</li>
                    <li><strong>Cookies:</strong> Personalizacion de colores del banner de cookies (fondo, texto, boton aceptar, boton rechazar)</li>
                    <li><strong>Cookies:</strong> Toggle para mostrar/ocultar icono de cookies en el footer</li>
                    <li><strong>Instagram Gallery:</strong> Panel de configuracion (Settings) para tenants con credenciales API, opciones de visualizacion y cache</li>
                    <li><strong>Instagram Gallery:</strong> Instrucciones paso a paso integradas en la pagina principal del modulo (configurar API, conectar cuenta, sincronizar, shortcodes)</li>
                    <li><strong>Instagram Gallery:</strong> Boton de shortcode/galeria en la tarjeta de cada conexion para generar shortcodes rapidamente</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Instagram Gallery:</strong> Corregido enlace "Configuracion" del panel tenant que daba 404 (faltaban rutas y controlador de Settings para tenant)</li>
                    <li><strong>Instagram Gallery:</strong> Corregidos todos los enlaces hardcodeados <code>/admin/</code> en vistas y controladores del tenant para usar <code>admin_path()</code> dinamico</li>
                    <li><strong>Domain Manager:</strong> Corregido boton "Añadir alias" que se quedaba en blanco (response.json() fallaba con errores PHP en HTML)</li>
                    <li><strong>Domain Manager:</strong> Corregido error 522 en dominios alias con proxy Cloudflare (CNAME apuntaba al servidor incorrecto)</li>
                    <li><strong>Domain Manager:</strong> Corregido ExecReload de Caddy que solo ejecutaba el script PHP de reparacion sin hacer el reload real</li>
                    <li><strong>Domain Manager:</strong> Corregido script repair-caddy-routes.php que fallaba al cargar la clase Env del panel (autoloader + try-catch)</li>
                    <li><strong>Temas:</strong> Corregido boton "Widgets" que daba 404 (faltaba slug del tema en la URL)</li>
                    <li><strong>Temas:</strong> Corregido breadcrumb en pagina de apariencia que se partia en dos lineas</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>Domain Manager:</strong> Dialog de eliminacion de alias ahora muestra exactamente que se borrara y permite elegir si eliminar de Cloudflare (con aviso de peligro para zonas completas)</li>
                    <li><strong>Domain Manager:</strong> Modal de progreso al añadir alias con fases visuales (validar, DB, Cloudflare, Caddy, completado)</li>
                    <li><strong>Domain Manager:</strong> Guia completa en modal explicando tokens CF, por que DNS-01, diferencia entre tokens principal y custom domains</li>
                    <li><strong>Domain Manager:</strong> Helper script + sudoers para actualizar token de Caddy desde PHP de forma segura</li>
                    <li><strong>Core:</strong> Metodo <code>Env::reload()</code> para forzar recarga de variables de entorno</li>
                    <li><strong>Temas:</strong> Borde del tema activo cambiado de verde grueso a azul fino</li>
                </ul>
            </div>
        </div>

        <!-- v2.4.1 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.4.1</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 26 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>Apariencia:</strong> Opciones de cookies en la seccion "Pie de pagina": toggle para mostrar/ocultar el enlace de configuracion de cookies en el footer</li>
                    <li><strong>Apariencia:</strong> Selector de icono de cookies con 6 opciones (emoji, cookie, cookie mordida, escudo, engranaje, sin icono)</li>
                    <li><strong>Apariencia:</strong> Selector de diseño del banner de cookies (tarjeta flotante, barra inferior, modal centrado) integrado en la pantalla de apariencia del tema</li>
                    <li><strong>Apariencia:</strong> Enlace directo a "Configuracion avanzada de cookies" desde la seccion de pie de pagina</li>
                    <li><strong>Apariencia:</strong> Nuevo tipo de opcion <code>link</code> en theme.json para enlazar a otras secciones del panel</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Cookies:</strong> Corregido banner de cookies en layout modal que no se cerraba al pulsar "Aceptar todas" o "Rechazar todo" (el <code>display: flex !important</code> del CSS sobreescribia el <code>display: none</code> del JS)</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>Cookies:</strong> Banner de cookies con resolucion inteligente de URLs legales: busca paginas publicadas del tenant por multiples slugs candidatos (igual que el footer), con fallback al setting del usuario</li>
                    <li><strong>Cookies:</strong> URLs de fallback del banner y footer ahora respetan el prefijo de paginas configurado (<code>page_url_prefix</code>) en vez de usar <code>/p/</code> hardcodeado</li>
                </ul>
            </div>
        </div>

        <!-- v2.4.0 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.4.0</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 25 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>WP Importer:</strong> Opcion "Importar como Briefs" para importar posts de WordPress como noticias breves (sin imagenes, con categoria "Brief" automatica)</li>
                    <li><strong>WP Importer:</strong> Fallback automatico a <code>?rest_route=</code> cuando la API REST de WordPress (<code>/wp-json/</code>) devuelve 404</li>
                    <li><strong>Blog:</strong> Nuevo tipo de post <code>post_type</code> (post/brief) con columna en blog_posts</li>
                    <li><strong>Blog:</strong> Seccion lateral de "Noticias Breves" configurable en blog e inicio (toggle + cantidad)</li>
                    <li><strong>Blog:</strong> Paginacion inteligente con elipsis para blogs con muchas paginas (sustituye listado completo)</li>
                    <li><strong>Blog:</strong> Traducciones i18n para seccion de briefs (es/en)</li>
                    <li><strong>Domain Manager:</strong> Configuracion de briefs (mostrar/cantidad) en la pagina de edicion del tenant</li>
                    <li><strong>AI Auto-Tagger:</strong> Boton de cancelar con temporizador visual durante el proceso de auto-etiquetado</li>
                    <li><strong>WP Importer:</strong> Deteccion mejorada de colores: resolucion de CSS variables <code>var()</code>, shorthand <code>background</code>, inline styles, WordPress block presets (<code>has-*-background-color</code>)</li>
                    <li><strong>WP Importer:</strong> Soporte ampliado de named colors CSS (30+ colores adicionales: navy, gray, teal, etc.)</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Media:</strong> Corregida perdida de transparencia en PNGs durante compresion (se convertian a JPEG perdiendo canal alpha)</li>
                    <li><strong>Media:</strong> Corregidos 71 seo_filenames rotos y 24 paths incorrectos en base de datos para archivos con puntos en el nombre original</li>
                    <li><strong>Lightbox:</strong> Corregido lightbox mostrando imagenes en miniatura (150x150) en vez del original</li>
                    <li><strong>Lightbox:</strong> Corregido funcionamiento del lightbox en todas las paginas (antes estaba hardcodeado para una sola pagina)</li>
                    <li><strong>AI Auto-Tagger:</strong> Corregido desbordamiento de context window de MiniMax con procesamiento por lotes (25 posts por batch)</li>
                    <li><strong>Theme:</strong> Corregido <code>&lt;p&gt;</code> que envolvia shortcodes de slider generando espacio extra entre slider y contenido</li>
                    <li><strong>Theme:</strong> Corregida regla CSS generica <code>main { padding-top: 0 }</code> que eliminaba espacio entre header y contenido en todos los tenants</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>Theme:</strong> Altura de sliders responsive en movil (220px en &lt;768px, 280px en tablets) para mejor proporcion visual</li>
                    <li><strong>Theme:</strong> Flechas de navegacion del slider dual-view reposicionadas en movil para evitar solapamiento con header</li>
                    <li><strong>Theme:</strong> Espacio optimizado entre header y contenido en movil (1rem) para mejor uso del espacio en pantalla</li>
                    <li><strong>Theme:</strong> Imagenes de contenido limitadas a max-width 100% para evitar desbordamiento</li>
                    <li><strong>Blog:</strong> Posts de tipo "brief" ocultan imagen destacada en todos los layouts (grid, list, magazine, newspaper, fashion)</li>
                    <li><strong>Blog:</strong> Estilos mejorados para boton "Ver todas" de briefs (pill-style sutil)</li>
                </ul>
            </div>
        </div>

        <!-- v2.3.0 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.3.0</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 23 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>Settings (Superadmin):</strong> Configuracion de prefijos de URL para blog y paginas en la seccion de Lectura (nivel global)</li>
                    <li><strong>WP Importer:</strong> Deteccion automatica de estructura de URLs de WordPress (sin prefijos) y ajuste automatico de prefijos en tenant_settings</li>
                    <li><strong>WP Importer:</strong> Progreso visual mejorado: spinner animado en la fase activa, reloj en fases pendientes, fondo destacado en fase actual</li>
                    <li><strong>WP Importer:</strong> Boton "Ver sitio" (target blank) en pantalla de resultado final</li>
                    <li><strong>Media Manager:</strong> Navegacion estilo Plesk con breadcrumbs y boton de volver atras en carpetas</li>
                    <li><strong>Media Manager:</strong> Toggle de vista cuadricula/lista con persistencia en localStorage</li>
                    <li><strong>Media Manager:</strong> Boton "Seleccionar todo" para acciones masivas</li>
                    <li><strong>Media Manager:</strong> Boton "Vaciar carpeta" para eliminar todos los archivos de una carpeta en una sola accion</li>
                    <li><strong>Theme:</strong> Header a ancho completo cuando el topbar esta desactivado</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Core:</strong> Corregido Page::updateSlug() que guardaba tenant_id como NULL en slugs, causando 404 en paginas importadas por tenant</li>
                    <li><strong>Core:</strong> SlugRouter ahora busca prefix IS NULL y prefix vacio para URLs sin prefijo</li>
                    <li><strong>Media Manager:</strong> Corregida paginacion error 500 por diferencia de keys entre QueryBuilder (current) y frontend (current_page)</li>
                    <li><strong>Media Manager:</strong> Pagina activa del paginador ahora se resalta correctamente</li>
                    <li><strong>Media Manager:</strong> Carpetas de tenant con parent_id NULL ahora se muestran correctamente en el arbol</li>
                    <li><strong>Media Manager:</strong> getFolderStructure() incluye root folder global para contexto tenant</li>
                    <li><strong>WP Importer:</strong> Carpeta de importacion ahora se vincula correctamente al root folder (parent_id correcto)</li>
                    <li><strong>WP Importer:</strong> Las imagenes importadas ahora se asocian correctamente a la carpeta "Import"</li>
                    <li><strong>Footer:</strong> Links legales ahora detectan paginas importadas de WordPress (politica-de-privacidad, terminos-y-condiciones-de-uso)</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>WP Importer:</strong> Carpeta de importacion renombrada de "WordPress Import" a "Import"</li>
                    <li><strong>WP Importer:</strong> No se crea carpeta vacia si no hay media para importar</li>
                    <li><strong>Footer:</strong> Query de paginas legales sin LIMIT 1 para respetar prioridad de slugs candidatos</li>
                </ul>
            </div>
        </div>

        <!-- v2.2.1 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.2.1</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 22 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>Domain Manager:</strong> Toggle de Cloudflare Proxy (nube naranja/gris) desde la pagina de edicion del tenant</li>
                    <li><strong>Domain Manager:</strong> Columna de email del admin en el listado de tenants</li>
                    <li><strong>Domain Manager:</strong> Cambio de contraseña del admin del tenant desde la pagina de edicion</li>
                    <li><strong>WP Importer:</strong> Importacion de menus de WordPress con jerarquia, asignacion automatica a location 'nav' y vinculacion de page_id</li>
                    <li><strong>WP Importer:</strong> Deteccion automatica de homepage de WordPress (show_on_front/page_on_front) y configuracion en tenant_settings</li>
                    <li><strong>Core:</strong> Pagina de Changelog accesible desde el footer</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Core:</strong> ErrorHandler y Route devuelven JSON para peticiones AJAX en lugar de HTML</li>
                    <li><strong>Core:</strong> Module loader corregido para cargar modulos cms_enabled en contexto superadmin bajo multi-tenancy</li>
                    <li><strong>WP Importer:</strong> Corregido TypeError en metodos findExisting* (ORM vs PDO)</li>
                    <li><strong>WP Importer:</strong> Corregido "items is not iterable" en preview de conflictos</li>
                    <li><strong>WP Importer:</strong> Corregido PDOStatement usado como array en resolveFeaturedImage()</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>Domain Manager:</strong> Sistema completo de Alias y Redirects con soporte Cloudflare y Caddy</li>
                </ul>
            </div>
        </div>

        <!-- v2.2.0 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.2.0</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Nuevas funcionalidades</h6>
                <ul class="mb-3">
                    <li><strong>Domain Manager:</strong> Gestion completa de dominios con Caddy + Cloudflare</li>
                    <li><strong>Domain Manager:</strong> Email Routing via Cloudflare</li>
                    <li><strong>Domain Manager:</strong> Registro y transferencia de dominios (OpenProvider)</li>
                    <li><strong>Domain Manager:</strong> DNS Manager para clientes</li>
                    <li><strong>Domain Manager:</strong> Panel de clientes con auto-registro</li>
                    <li><strong>WP Importer:</strong> Importador de WordPress via REST API (posts, paginas, categorias, tags, media, estilos)</li>
                    <li><strong>Core:</strong> Sistema multi-tenant con aislamiento completo</li>
                    <li><strong>Core:</strong> Cross-Publisher entre grupos editoriales</li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection
