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

        <!-- v2.6.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.6.0</h5>
                <span class="badge bg-success">Latest</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 31 de Marzo de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Sistema de tipografia del contenido</h6>
                <ul class="mb-3">
                    <li><strong>Panel de apariencia:</strong> Nueva seccion "Tipografia del contenido" con selector de fuentes para titulos y texto, escalas tipograficas y colores</li>
                    <li><strong>TinyMCE WYSIWYG:</strong> El editor ahora refleja las fuentes y tamanos del tema en tiempo real</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Selector de fuentes en TinyMCE</h6>
                <ul class="mb-3">
                    <li><strong>23 Google Fonts curadas:</strong> Desplegable de fuentes con Sans-serif, Serif, Monospace y Display</li>
                    <li><strong>Selector de tamano:</strong> Tamanos de 12px a 48px en la toolbar</li>
                    <li><strong>Carga automatica:</strong> Las fuentes usadas en el contenido se detectan y cargan solo donde se necesitan</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Mejoras de TinyMCE</h6>
                <ul class="mb-3">
                    <li><strong>Tablas avanzadas:</strong> Propiedades de color, bordes y ancho por tabla, fila y celda</li>
                    <li><strong>Lightbox configurable:</strong> Toggle para activar/desactivar lightbox automatico en imagenes</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li>Slug doble barra en creacion de paginas, pagina de inicio no se guardaba, imagenes centradas, tablas responsive, lightbox PNG transparente</li>
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
                    <li><strong>Estructura de pagina:</strong> Nueva jerarquia Tema > Estructura > Skin. La seccion "Estructura de pagina" permite elegir entre Clasica (cabecera + pie) o Sidebar lateral (portfolio/personal)</li>
                    <li><strong>Estructura Sidebar:</strong> Opciones propias: selector de idioma, redes sociales, buscador y boton CTA, cada uno activable/desactivable independientemente</li>
                    <li><strong>Estructura Sidebar:</strong> Fuerza automaticamente ancho completo en todos los posts y paginas. El selector de plantilla se desactiva con nota informativa</li>
                    <li><strong>Dependencias de seccion:</strong> Las secciones completas (Topbar, Cabecera, Pie) se ocultan/muestran segun la estructura elegida</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> AI Skin Generator (nuevo plugin)</h6>
                <ul class="mb-3">
                    <li><strong>Generacion con IA:</strong> Genera skins completos describiendo el estilo deseado. La IA conoce todos los layouts, funcionalidades y sus combinaciones recomendadas</li>
                    <li><strong>Consultor de proyecto:</strong> Paso opcional donde la IA analiza tu proyecto y recomienda sector, contenido, publico objetivo y genera la descripcion del skin</li>
                    <li><strong>Skin aleatorio:</strong> Genera skins sin IA mezclando colores de 15 paletas predefinidas con layouts al azar</li>
                    <li><strong>Mezclar layouts:</strong> Cambia headers, footers y blog layouts al azar sin tocar colores</li>
                    <li><strong>Fijar estructura:</strong> 10 plantillas base (portfolio, periodico, revista, blog limpio, etc.) que fijan layouts y solo varian colores/tipografias</li>
                    <li><strong>Refinamiento:</strong> Pide cambios a la IA sobre el skin generado con modales de loading</li>
                    <li><strong>20 prompts de ejemplo:</strong> Boton de dado con descripciones completas para distintos tipos de medios</li>
                    <li><strong>Vista previa dinamica:</strong> Preview que refleja cada layout de header (default, centered, logo-above, sidebar, banner...), blog (grid, newspaper, magazine, fashion...) y footer (clasico, banner, minimal)</li>
                    <li><strong>Validacion automatica:</strong> Corrige contraste (fondo oscuro/texto claro), valores invalidos de select y normaliza toggles</li>
                    <li><strong>Color pickers:</strong> 3 selectores de color nativos (primario, acento, fondo) para colores de marca</li>
                    <li><strong>Deteccion de sobreescritura:</strong> Modal de confirmacion al guardar skin con nombre existente</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Gestion de skins</h6>
                <ul class="mb-3">
                    <li><strong>Domain Manager:</strong> Selector rapido de skin en el panel lateral de edicion de tenant</li>
                    <li><strong>Domain Manager:</strong> Gestion de presets (guardar, cargar, eliminar) por tenant desde el superadmin</li>
                    <li><strong>Temas (superadmin):</strong> Botones de activar/desactivar y eliminar skins al pasar el raton</li>
                    <li><strong>Cookie banner:</strong> Al aplicar skin, los colores de los botones del banner de cookies se adaptan automaticamente al color de acento</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Header y Topbar</h6>
                <ul class="mb-3">
                    <li><strong>Header modular:</strong> Nuevas opciones: iconos de redes sociales, fecha/reloj y buscador en el header, independientes del topbar</li>
                    <li><strong>Layout logo-above-left:</strong> Redes + lupa a la derecha del logo, fecha + reloj a la derecha del menu</li>
                    <li><strong>Topbar ticker:</strong> Latest Post integrado en la misma linea del topbar (no como fila separada)</li>
                    <li><strong>Topbar modular:</strong> Toggle para mostrar/ocultar redes sociales del topbar</li>
                    <li><strong>Opciones reubicadas:</strong> Reloj, ticker clock, formato y zona horaria movidos de Blog a Barra superior</li>
                    <li><strong>Ticker personalizable:</strong> 7 opciones de color para la barra de Top Tags + Latest Post</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Blog</h6>
                <ul class="mb-3">
                    <li><strong>Newspaper overlay:</strong> Opcion para superponer titulo, categorias, autor y fecha sobre la imagen en posts destacados</li>
                    <li><strong>Plantilla de post:</strong> El selector ahora funciona realmente: Con Sidebar Derecha (default), Ancho Completo, Con Sidebar Izquierda</li>
                    <li><strong>Cards de categorias:</strong> Nuevo diseno con imagen del ultimo post, contador circular, flecha animada y hover</li>
                    <li><strong>Imagenes no repetidas:</strong> Las cards de categorias evitan repetir la misma imagen entre categorias distintas</li>
                    <li><strong>Titulos redundantes:</strong> Eliminados h1 de paginas /category, /tag y /tags (la miga de pan es suficiente)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Footer</h6>
                <ul class="mb-3">
                    <li><strong>Footer minimal:</strong> Nuevo layout minimalista con solo copyright (alineado izquierda) + enlaces legales en fondo blanco</li>
                    <li><strong>Cookie banner links:</strong> Politica de Cookies y Terminos alineados a la izquierda en layout bar</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-bug me-1"></i> Correcciones</h6>
                <ul class="mb-3">
                    <li><strong>Presets:</strong> Corregida query ON DUPLICATE KEY UPDATE a ON CONFLICT para PostgreSQL</li>
                    <li><strong>Select de idiomas:</strong> Color naranja hardcodeado reemplazado por variable CSS del tema</li>
                    <li><strong>Cross-Publisher:</strong> El estado "Publicado" en settings ahora se respeta al copiar posts entre tenants</li>
                    <li><strong>Traducciones:</strong> Añadida seccion "search" en los archivos de traduccion (tenant y superadmin, ES/EN)</li>
                    <li><strong>Footer boxed:</strong> Footer minimal respeta el ancho del contenido en modo boxed</li>
                    <li><strong>Depends_on:</strong> Soporte para dependencias con valor especifico (seccion.opcion=valor) en opciones y secciones completas</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>AI Prompts:</strong> Reglas de contraste obligatorias, guia de layouts y combinaciones recomendadas para la IA</li>
                    <li><strong>Retrocompatibilidad:</strong> Tenants con header_layout=sidebar migran automaticamente a la nueva estructura sin romperse</li>
                    <li><strong>UX:</strong> Modales SweetAlert2 para refinar, guardar y sobreescribir skins</li>
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
                    <li><strong>Temas:</strong> Corregido boton "Widgets" que daba 404 (faltaba slug del tema en la URL)</li>
                    <li><strong>Temas:</strong> Corregido breadcrumb en pagina de apariencia que se partia en dos lineas</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-arrow-repeat me-1"></i> Mejoras</h6>
                <ul class="mb-0">
                    <li><strong>Instagram Gallery:</strong> Pagina principal mejorada con degradado Instagram en el avatar, boton de galeria/shortcode y tarjeta de instrucciones</li>
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
