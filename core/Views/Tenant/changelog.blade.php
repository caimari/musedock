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

        <!-- v2.18.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.18.0</h5>
                <span class="badge bg-success">Latest</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 27 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-hdd-network me-1"></i> DNS y certificados para dominios</h6>
                <ul class="mb-3">
                    <li><strong>Soporte multi-proveedor:</strong> el panel puede guardar proveedor DNS por dominio, alias y redireccion</li>
                    <li><strong>DNS-01:</strong> preparada la base para emitir certificados cuando el dominio esta detras de proxy o con 80/443 cerrados</li>
                    <li><strong>Proveedores:</strong> Cloudflare conserva el flujo actual y se añaden cuentas DNS para DigitalOcean, Route53, Hetzner, OVH, Vultr, Linode, Porkbun, Namecheap, Gandi, PowerDNS y RFC2136</li>
                    <li><strong>Sin cambios automaticos en dominios existentes:</strong> los hostings ya creados mantienen su configuracion</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-shield-check me-1"></i> Mejoras operativas</h6>
                <ul class="mb-0">
                    <li><strong>Diagnostico:</strong> el panel informa mejor cuando faltan credenciales DNS o cuando un proveedor requiere configuracion manual</li>
                    <li><strong>Version:</strong> Caddy Domain Manager actualizado a <code>1.3.0</code></li>
                </ul>
            </div>
        </div>

        <!-- v2.17.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.17.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 21 de Abril de 2026</p>

                <h6 class="text-success"><i class="bi bi-check2-circle me-1"></i> Cierre de Fases (i18n)</h6>
                <ul class="mb-3">
                    <li><strong>Fase 0 cerrada:</strong> Infraestructura de traducciones (JSON + BD), migracion y editor de overrides en CMS principal</li>
                    <li><strong>Fase 1 cerrada:</strong> Modulo <code>Pages</code> (CMS + Tenant) traducido en vistas, JS/modales, revisiones, papelera, bulk edit y controladores</li>
                    <li><strong>Paridad validada:</strong> Claves <code>pages/common</code> sincronizadas en <code>lang/superadmin</code> y <code>lang/tenant</code> para <code>es/en</code></li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-translate me-1"></i> Motor de Traducciones con Overrides</h6>
                <ul class="mb-3">
                    <li><strong>Nuevo almacenamiento en base de datos:</strong> Tabla <code>translation_overrides</code> para personalizar textos sin editar archivos JSON</li>
                    <li><strong>Resolucion hibrida:</strong> El sistema combina <em>JSON base + overrides BD por clave</em>. Solo las claves editadas reemplazan al JSON</li>
                    <li><strong>Fallback conservado:</strong> Si una clave no existe en el idioma actual, se mantiene el fallback a <code>es</code></li>
                </ul>

                <h6 class="text-info"><i class="bi bi-sliders me-1"></i> Editor de Traducciones en CMS Principal</h6>
                <ul class="mb-3">
                    <li><strong>Nueva pantalla:</strong> <code>/musedock/languages/translations</code> con filtros por contexto, idioma y buscador por clave/texto</li>
                    <li><strong>Acciones por clave:</strong> Guardar override y restablecer al valor base del JSON</li>
                    <li><strong>Ayuda en UI:</strong> Bloque <em>Como funciona</em> visible dentro del editor</li>
                </ul>

                <h6 class="text-warning"><i class="bi bi-diagram-3 me-1"></i> Overrides por Tenant desde Superadmin</h6>
                <ul class="mb-3">
                    <li><strong>Nuevo ambito:</strong> Selector Global o Tenant especifico en el editor</li>
                    <li><strong>Guardado por scope:</strong> Los overrides se guardan como globales (<code>tenant_id=0</code>) o por tenant (<code>tenant_id=&gt;0</code>)</li>
                    <li><strong>Badge contextual:</strong> Indicador visual de ambito activo (Global / Tenant seleccionado)</li>
                </ul>

                <h6 class="text-secondary"><i class="bi bi-arrow-repeat me-1"></i> Ajustes de Idioma en Tenant</h6>
                <ul class="mb-0">
                    <li><strong>Ruta de cambio de idioma corregida:</strong> El switcher tenant ya usa <code>admin_path</code> dinamico y evita hardcode <code>/admin</code></li>
                    <li><strong>Redirect seguro:</strong> Fallback a <code>admin_url('dashboard')</code> cuando no hay redireccion valida</li>
                </ul>
            </div>
        </div>

        <!-- v2.16.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.16.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 12 de Abril de 2026</p>

                <h6 class="text-danger"><i class="bi bi-megaphone me-1"></i> Social Publisher (antes Instagram Gallery)</h6>
                <ul class="mb-3">
                    <li><strong>Nuevo nombre y URL:</strong> El modulo pasa a llamarse <strong>Social Publisher</strong>. La URL cambia de <code>/admin/instagram</code> a <code>/admin/social-publisher</code> (los enlaces viejos redirigen automaticamente)</li>
                    <li><strong>Publicacion en Facebook:</strong> Ya puedes vincular la Pagina de Facebook asociada a tu cuenta de Instagram Business. Al publicar un post del blog, puedes elegir en que red publicarlo (IG, FB, o ambas)</li>
                    <li><strong>API nueva de Instagram:</strong> Migrado a la «Instagram API con inicio de sesion para empresas de Instagram» (la que sustituyo a Basic Display API)</li>
                    <li><strong>Hashtags de marca:</strong> Cada cuenta tiene sus hashtags predefinidos configurables. Se combinan automaticamente con categorias y tags del post al publicar (prioridad a los preset, limite de 30)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-newspaper me-1"></i> Compartir posts del blog</h6>
                <ul class="mb-3">
                    <li><strong>Boton «Compartir» en /admin/blog/posts:</strong> Aparece en cada post publicado con imagen destacada cuando Social Publisher tiene una cuenta valida</li>
                    <li><strong>Modal con preview:</strong> Imagen, selector de cuenta, checkboxes para IG/FB, caption editable con contador 0/2200. Facebook recibe el link como preview automatico; Instagram lo lleva como texto</li>
                    <li><strong>Badges de publicacion:</strong> Los posts muestran badge IG (rosa) y FB (azul) con link directo al permalink publicado en cada red</li>
                    <li><strong>Caption inteligente:</strong> Si el post no tiene excerpt, se usa un teaser con los H2 del contenido en lugar del cuerpo completo (no desvela el articulo)</li>
                </ul>

                <h6 class="text-warning"><i class="bi bi-ui-radios me-1"></i> Blog mosaico</h6>
                <ul class="mb-3">
                    <li><strong>Card hero con categorias:</strong> La tarjeta grande del layout mosaico ya muestra chips de categoria sobre el titulo, igual que las pequeñas</li>
                    <li><strong>Chips clicables:</strong> Al pulsarlos llevan al listado de esa categoria/tag, manteniendo color blanco (no se pinta con el color visited del tema)</li>
                </ul>

                <h6 class="text-secondary"><i class="bi bi-list me-1"></i> Header</h6>
                <ul class="mb-0">
                    <li><strong>Hamburguesa inteligente:</strong> El boton de menu movil deja de aparecer si el menu esta vacio. Arreglado en todos los layouts del tema default</li>
                </ul>
            </div>
        </div>

        <!-- v2.15.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.15.0</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 12 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-chat-left-text me-1"></i> Sistema de comentarios en Blog</h6>
                <ul class="mb-3">
                    <li><strong>Comentarios publicos activables por post:</strong> Formulario en frontend default con nombre, email, web opcional y comentario</li>
                    <li><strong>Panel de moderacion:</strong> Nueva seccion de comentarios con filtros por estado y acciones (aprobar, spam, eliminar)</li>
                    <li><strong>Menu lateral:</strong> Nuevo item <code>Comentarios</code> dentro de <code>Blog</code></li>
                </ul>

                <h6 class="text-warning"><i class="bi bi-shield-check me-1"></i> Moderacion y Anti-spam</h6>
                <ul class="mb-3">
                    <li><strong>Modo de aprobacion configurable:</strong> Manual, autoaprobar todos o autoaprobar solo autores previamente aprobados (recomendado)</li>
                    <li><strong>Ajustes en Lectura:</strong> Configuracion centralizada en <code>/{admin_path}/settings/reading</code></li>
                    <li><strong>Umbral de enlaces spam:</strong> Regla configurable para deteccion automatica de spam</li>
                    <li><strong>CAPTCHA adaptativo:</strong> Solo aparece cuando hay pico de spam en las ultimas 24h</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-bell me-1"></i> Notificaciones</h6>
                <ul class="mb-3">
                    <li><strong>Campana en dashboard:</strong> Aviso inmediato cuando entra un comentario pendiente</li>
                    <li><strong>Enlace directo:</strong> La notificacion abre la bandeja de comentarios pendientes</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-shield-lock me-1"></i> RGPD</h6>
                <ul class="mb-0">
                    <li><strong>Consentimiento obligatorio:</strong> Checkbox con enlaces a Privacidad y Terminos antes de enviar comentario</li>
                    <li><strong>Registro de consentimiento:</strong> Se guarda estado y fecha/hora de aceptacion por comentario</li>
                    <li><strong>Transparencia legal:</strong> Las plantillas legales incluyen ya el tratamiento de datos de comentarios</li>
                </ul>
            </div>
        </div>

        <!-- v2.14.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.14.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 6 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-shield-check me-1"></i> Legal Templates Engine</h6>
                <ul class="mb-3">
                    <li><strong>Datos legales:</strong> Nueva seccion en Configuracion con jurisdiccion, tipo de titular, NIF/CIF, email legal, domicilio, datos registrales y autoridad de control</li>
                    <li><strong>Paginas legales automaticas:</strong> Aviso Legal, Privacidad (RGPD completo), Cookies (condicional) y Terminos y Condiciones generados automaticamente</li>
                    <li><strong>Multi-jurisdiccion:</strong> Adapta labels y contenido segun pais (ES/EU/US/BR/MX/AR)</li>
                    <li><strong>Datos independientes:</strong> Email y domicilio legal separados de los datos de contacto publicos</li>
                </ul>

                <h6 class="text-success"><i class="bi bi-book me-1"></i> Documentacion Tecnica</h6>
                <ul class="mb-3">
                    <li><strong>Tipo de post "Documentacion":</strong> Selector en el formulario de creacion con prefix automatico <code>/docs/</code></li>
                    <li><strong>Template docs:</strong> Sidebar de navegacion, Table of Contents automatico, breadcrumbs, boton copiar en codigo</li>
                </ul>

                <h6 class="text-info"><i class="bi bi-palette me-1"></i> Frontend & UX</h6>
                <ul class="mb-3">
                    <li><strong>Header responsive:</strong> Logo + hamburguesa alineados en mobile</li>
                    <li><strong>Busqueda modal:</strong> Opcion modal overlay o pagina de busqueda en header</li>
                    <li><strong>Categorias limitadas:</strong> Max 3 aleatorias en portada para evitar overflow visual</li>
                    <li><strong>Candado SEO:</strong> Bloqueo de campos SEO/Twitter para evitar autocomplete</li>
                    <li><strong>Paginas con titulo subrayado:</strong> Diseño mejorado para paginas estaticas</li>
                </ul>
            </div>
        </div>

        <!-- v2.13.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.13.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 6 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Menus inteligentes y URLs limpias</h6>
                <ul class="mb-3">
                    <li><strong>Auto-sync menu links:</strong> Al cambiar prefijos de URL en Ajustes > Lectura, los links de los menus se actualizan automaticamente</li>
                    <li><strong>Homepage detection:</strong> Los menus detectan la pagina de inicio y enlazan a <code>/</code>. Redirect 301 al acceder al slug directo</li>
                    <li><strong>Sin prefijo por defecto:</strong> Nuevos tenants sin prefijo <code>/p/</code> — URLs limpias</li>
                    <li><strong>Redirect 301 legacy WordPress:</strong> URLs tipo <code>/index.php/YYYY/MM/DD/slug</code>, <code>/index.php/tag/slug</code> y <code>/index.php/category/slug</code></li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Diseño admin mejorado</h6>
                <ul class="mb-3">
                    <li><strong>Cabecera estandar:</strong> Nuevo patron visual para todas las paginas admin con icono degradado + titulo + stat-badges</li>
                    <li><strong>Toggle switches:</strong> Activar/desactivar modulos con toggle switches estilo iOS</li>
                    <li><strong>Visitar sitio:</strong> Enlace "Visitar sitio" en el menu de usuario (target blank)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-instagram me-1"></i> Instagram Gallery: oEmbed + Widget</h6>
                <ul class="mb-3">
                    <li><strong>oEmbed (sin API):</strong> Shortcode <code>[instagram-post url="..."]</code> para insertar posts publicos sin configurar credenciales</li>
                    <li><strong>Selector de modo:</strong> Graph API, oEmbed, o Ambos en la configuracion del modulo</li>
                    <li><strong>Widget Instagram Feed:</strong> Nuevo widget en Apariencia > Widgets con modo Feed u oEmbed</li>
                    <li><strong>Generador de shortcodes:</strong> UI interactiva para generar shortcodes — pegar URL o configurar feed visualmente</li>
                    <li><strong>Credenciales protegidas:</strong> Inputs API bloqueados con boton "Desbloquear para editar"</li>
                    <li><strong>Redirect URI auto:</strong> Pre-rellenada con el dominio del sitio + boton copiar</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> SEO y lectura</h6>
                <ul class="mb-3">
                    <li><strong>Posts por pagina:</strong> Default 9 (grid simetrico 3x3)</li>
                    <li><strong>Nota feed RSS:</strong> Texto explicativo en Ajustes > Lectura</li>
                </ul>
            </div>
        </div>

        <!-- v2.12.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.12.0</h5>
                <span class="badge bg-secondary">Previous</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 5 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> API REST v1</h6>
                <ul class="mb-3">
                    <li><strong>API REST completa:</strong> Nuevos endpoints <code>/api/v1/</code> para gestionar posts, paginas, categorias y tags via HTTP con autenticacion Bearer token</li>
                    <li><strong>CRUD completo:</strong> Create, Read, Update, Delete para posts, paginas, categorias, tags. Creacion automatica de categorias/tags al publicar posts</li>
                    <li><strong>Descarga de imagenes:</strong> Al crear un post con <code>featured_image_url</code>, la imagen se descarga y almacena automaticamente en el Media Manager</li>
                    <li><strong>OpenAPI schema:</strong> Endpoint <code>/api/v1/openapi.yaml</code> para configurar ChatGPT Custom GPTs (Actions)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> API Keys y Autenticacion</h6>
                <ul class="mb-3">
                    <li><strong>Sistema de API Keys:</strong> Generacion de keys con prefijo <code>mdk_</code>, hash SHA-256, se muestra una sola vez al crearla</li>
                    <li><strong>Permisos granulares:</strong> 17 permisos individuales por recurso y accion (ej: <code>posts.create</code>, <code>pages.delete</code>, <code>tags.read</code>)</li>
                    <li><strong>Keys de tenant:</strong> Cada tenant puede crear sus propias API keys desde Ajustes > API Keys, limitadas a su sitio</li>
                    <li><strong>Confirmacion de acciones peligrosas:</strong> Los endpoints de eliminacion requieren <code>"confirm": true</code> (HTTP 428)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Rate Limiting y Logging</h6>
                <ul class="mb-3">
                    <li><strong>Rate limit global:</strong> Configurable por key (default 60 req/min)</li>
                    <li><strong>Rate limit por accion:</strong> Limites especificos para acciones destructivas (<code>delete</code>: 5/min, <code>create</code>: 15/min)</li>
                    <li><strong>Logging completo:</strong> Registro de cada llamada API con tool, duracion, status e IP</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Integraciones</h6>
                <ul class="mb-3">
                    <li><strong>Zapier / Make / n8n:</strong> Conecta workflows de automatizacion directamente via REST</li>
                    <li><strong>ChatGPT Custom GPTs:</strong> Configura Actions con el schema OpenAPI para publicar desde el chat</li>
                    <li><strong>Claude Code / Desktop:</strong> MCP Server integrado para gestionar contenido via IA</li>
                    <li><strong>Panel de gestion:</strong> Nueva seccion Ajustes > API Keys con URLs listas para copiar</li>
                </ul>
            </div>
        </div>

        <!-- v2.11.0 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>v2.11.0</h5>
                <span class="badge bg-secondary">5 Abr 2026</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><i class="bi bi-calendar3 me-1"></i> 5 de Abril de 2026</p>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Rendimiento movil</h6>
                <ul class="mb-3">
                    <li><strong>CSS diferido:</strong> 11 archivos CSS no criticos cargados con <code>media="print" onload</code> (slicknav, animate, magnific-popup, Swiper, Slick, Owl Carousel, cookie-consent, nice-select2)</li>
                    <li><strong>Google Fonts:</strong> Carga async con <code>media="print"</code> + fallback <code>&lt;noscript&gt;</code></li>
                    <li><strong>CSS critico:</strong> Solo 6 archivos bloqueantes (Bootstrap, FontAwesome, Themify, style, responsive, template/custom)</li>
                    <li><strong>Ahorro estimado:</strong> ~1800ms de tiempo de bloqueo eliminado en movil</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Accesibilidad</h6>
                <ul class="mb-3">
                    <li><strong>aria-label:</strong> Añadido a todos los iconos sociales del footer (Facebook, Instagram, Twitter, etc.) en los 3 layouts de footer</li>
                    <li><strong>aria-label:</strong> Añadido a selectores de idioma (footer, mobile menu, sidebar)</li>
                    <li><strong>Areas tactiles:</strong> Iconos sociales del footer con minimo 44x44px para cumplir requisitos moviles</li>
                    <li><strong>Encabezados semanticos:</strong> Footer <code>&lt;h4&gt;</code> reemplazados por <code>&lt;div class="footer-heading"&gt;</code> para evitar saltos de jerarquia</li>
                    <li><strong>rel="noopener":</strong> Añadido a todos los enlaces sociales <code>target="_blank"</code></li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> SEO</h6>
                <ul class="mb-3">
                    <li><strong>Enlaces rastreables:</strong> Enlace de cookies cambiado de <code>&lt;a href="javascript:void(0)"&gt;</code> a <code>&lt;button&gt;</code> en los 3 layouts de footer</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> TinyMCE: Codesample</h6>
                <ul class="mb-3">
                    <li><strong>Proteccion de codigo:</strong> Handler BeforeSetContent que preserva entidades HTML dentro de bloques <code>&lt;pre&gt;&lt;code&gt;</code> al editar</li>
                    <li><strong>Doble clic:</strong> Doble clic en bloques de codigo abre el dialogo de edicion de codesample</li>
                    <li><strong>valid_children:</strong> Configuracion <code>+pre[code]</code> y <code>pre/code</code> en extended_valid_elements</li>
                    <li><strong>Lenguajes:</strong> 8 lenguajes configurados (HTML/XML, CSS, JavaScript, PHP, Bash, SQL, JSON, Python)</li>
                </ul>

                <h6 class="text-primary"><i class="bi bi-stars me-1"></i> Frontend: Syntax Highlighting</h6>
                <ul class="mb-3">
                    <li><strong>Prism.js:</strong> Syntax highlighting en el frontend para bloques de codigo insertados con codesample</li>
                    <li><strong>Tema Tomorrow:</strong> Tema oscuro para bloques de codigo</li>
                    <li><strong>Carga condicional:</strong> JS solo se carga si la pagina contiene bloques <code>&lt;pre&gt;&lt;code class="language-*"&gt;</code></li>
                    <li><strong>8 lenguajes:</strong> markup, CSS, JavaScript, PHP, Bash, SQL, JSON, Python</li>
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
