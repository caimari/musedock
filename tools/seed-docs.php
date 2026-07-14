<?php
/**
 * Seed documentation articles for MuseDock products
 * Run: php tools/seed-docs.php
 */
require __DIR__ . '/../core/bootstrap.php';

use Screenart\Musedock\Database;

$now = date('Y-m-d H:i:s');
$pdo = Database::connect();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

/**
 * Parse CLI options (e.g. --tenant=36 --domain=musedock.com)
 */
function cliOptions(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--') !== 0) continue;
        $eq = strpos($arg, '=');
        if ($eq === false) {
            $opts[substr($arg, 2)] = true;
            continue;
        }
        $k = substr($arg, 2, $eq - 2);
        $v = substr($arg, $eq + 1);
        $opts[$k] = $v;
    }
    return $opts;
}

$opts = cliOptions($argv ?? []);
$targetTenantId = null;
if (isset($opts['tenant']) && $opts['tenant'] !== '') {
    $targetTenantId = (int)$opts['tenant'];
}
if ($targetTenantId === null && isset($opts['domain']) && $opts['domain'] !== '') {
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE domain = ? LIMIT 1');
    $stmt->execute([$opts['domain']]);
    $found = $stmt->fetchColumn();
    if ($found) {
        $targetTenantId = (int)$found;
    } else {
        throw new RuntimeException("No se encontró tenant para domain={$opts['domain']}");
    }
}

$tenantLabel = $targetTenantId === null ? 'GLOBAL (tenant_id NULL)' : ("TENANT {$targetTenantId}");
echo "Target scope: {$tenantLabel}\n";

// ===== CMS DOCS =====
$cmsDocs = [
    [
        'title' => 'Introducción a MuseDock CMS',
        'slug' => 'introduccion-musedock-cms',
        'excerpt' => 'Guía de presentación de MuseDock CMS: arquitectura, flujo de trabajo, ventajas para agencias y empresas de hosting, y checklist de arranque.',
        'content' => '<h2>Qué es MuseDock CMS</h2>
<p><strong>MuseDock CMS</strong> es un gestor de contenidos multi-tenant orientado a producción real. Permite administrar múltiples sitios desde una sola instalación, con panel de superadmin, panel por tenant, sistema modular y documentación integrada.</p>

<h2>Para quién está pensado</h2>
<ul>
<li><strong>Empresas de hosting</strong> que quieren ofrecer CMS gestionado a sus clientes.</li>
<li><strong>Agencias web</strong> que necesitan crear y mantener múltiples proyectos con una base común.</li>
<li><strong>Equipos técnicos</strong> que buscan control de despliegue, dominios, SEO y extensibilidad.</li>
</ul>

<h2>Arquitectura resumida</h2>
<ul>
<li><strong>Núcleo MVC + Blade</strong> para mantener el frontend desacoplado del backend.</li>
<li><strong>Multi-tenant</strong> con separación por dominio y configuración por tenant.</li>
<li><strong>Módulos y plugins</strong> para extender funcionalidad sin tocar el core.</li>
<li><strong>Blog/Docs integrados</strong> con categorías, breadcrumbs y navegación lateral.</li>
<li><strong>SEO nativo</strong> (meta tags, Open Graph, canonical, robots, sitemap).</li>
</ul>

<h2>Qué puedes hacer desde el primer día</h2>
<ol>
<li>Crear páginas y posts con editor visual.</li>
<li>Configurar tema, menús, header/footer y bloques reutilizables.</li>
<li>Publicar documentación en <code>/docs</code> con sidebar por producto.</li>
<li>Gestionar formularios, newsletter y flujos de comunicación.</li>
<li>Operar varios sitios con una sola instancia de CMS.</li>
</ol>

<h2>Ventajas frente a un CMS monositio</h2>
<ul>
<li>Menos coste operativo por centralizar mantenimiento.</li>
<li>Mayor coherencia entre proyectos (temas, módulos, procesos).</li>
<li>Escalado más simple para nuevos dominios/clientes.</li>
<li>Mejor gobernanza para equipos técnicos y de contenido.</li>
</ul>

<h2>Checklist recomendado de arranque</h2>
<ul>
<li>Definir dominio principal y política multi-tenant.</li>
<li>Configurar SMTP global y política SMTP por tenant.</li>
<li>Revisar SEO base (título de sitio, sitemap, robots, canonical).</li>
<li>Crear estructura inicial de docs (Presentación, Configuración, etc.).</li>
<li>Activar backups y revisar permisos de acceso al panel.</li>
</ul>

<h2>Siguiente paso</h2>
<p>Continúa con <strong>Primeros pasos con MuseDock CMS</strong> para crear tu primera página y dejar el entorno listo para producción.</p>',
        'seo_title' => 'Introducción a MuseDock CMS - Guía general para empezar',
        'seo_description' => 'Presentación de MuseDock CMS: qué es, cómo funciona, ventajas multi-tenant y pasos recomendados para arrancar en producción.',
        'seo_keywords' => 'introducción musedock cms, cms multi-tenant, guía musedock, hosting cms, documentación musedock',
    ],
    [
        'title' => 'Instalación de MuseDock CMS',
        'slug' => 'instalacion-musedock-cms',
        'excerpt' => 'Guía completa para instalar MuseDock CMS en tu servidor. Requisitos, instalación paso a paso y configuración inicial.',
        'content' => '<h2>Requisitos del sistema</h2>
<p>MuseDock CMS requiere:</p>
<ul>
<li><strong>PHP 8.1+</strong> con extensiones: pdo, pdo_pgsql, mbstring, json, openssl, fileinfo, gd o imagick</li>
<li><strong>PostgreSQL 13+</strong> (también compatible con MySQL 8+)</li>
<li><strong>Caddy</strong> o Nginx como servidor web</li>
<li><strong>Composer 2.x</strong></li>
<li>Mínimo 512MB RAM, 1GB de espacio en disco</li>
</ul>

<h2>Instalación rápida</h2>
<ol>
<li>Clonar el repositorio: <code>git clone https://github.com/caimari/musedock.git</code></li>
<li>Instalar dependencias: <code>composer install --no-dev</code></li>
<li>Copiar <code>.env.example</code> a <code>.env</code> y configurar</li>
<li>Ejecutar el instalador: visitar <code>https://tudominio.com/install/</code></li>
<li>El instalador crea la base de datos, usuario admin y configuración inicial</li>
</ol>

<h2>Configuración del entorno</h2>
<p>Variables principales del archivo <code>.env</code>:</p>
<ul>
<li><code>APP_URL</code> - URL de tu sitio</li>
<li><code>DB_DRIVER</code> - <code>pgsql</code> o <code>mysql</code></li>
<li><code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code> - Conexión a base de datos</li>
<li><code>APP_DEBUG</code> - <code>true</code> en desarrollo, <code>false</code> en producción</li>
</ul>

<h2>Permisos</h2>
<p>Asegurar permisos de escritura en:</p>
<ul>
<li><code>storage/</code> - Logs, caché y vistas compiladas</li>
<li><code>public/uploads/</code> - Archivos subidos</li>
<li><code>public/media/</code> - Media Manager</li>
</ul>',
        'seo_title' => 'Instalación de MuseDock CMS - Guía paso a paso',
        'seo_description' => 'Guía completa para instalar MuseDock CMS. Requisitos del sistema, instalación paso a paso, configuración de entorno y permisos.',
        'seo_keywords' => 'instalación, CMS, setup, configuración, MuseDock, self-hosted, deploy',
    ],
    [
        'title' => 'Primeros pasos con MuseDock CMS',
        'slug' => 'primeros-pasos-musedock-cms',
        'excerpt' => 'Aprende a crear tu primera página, configurar el tema y publicar contenido con MuseDock CMS.',
        'content' => '<h2>Panel de administración</h2>
<p>Tras la instalación, accede al panel en <code>https://tudominio.com/admin/</code>. Desde aquí gestionas:</p>
<ul>
<li><strong>Páginas</strong> - Crea y edita páginas con el editor visual</li>
<li><strong>Blog</strong> - Artículos con categorías, etiquetas y SEO</li>
<li><strong>Media</strong> - Gestor de archivos multimedia</li>
<li><strong>Temas</strong> - Personalización visual</li>
<li><strong>Módulos</strong> - Funcionalidades adicionales</li>
<li><strong>Usuarios</strong> - Gestión de permisos y roles</li>
</ul>

<h2>Crear tu primera página</h2>
<ol>
<li>Ve a <strong>Páginas → Nueva página</strong></li>
<li>Escribe el título y contenido usando el editor TinyMCE</li>
<li>Configura SEO (título, descripción, imagen OG)</li>
<li>Elige una plantilla de página</li>
<li>Publica la página</li>
</ol>

<h2>Configurar el tema</h2>
<p>Ve a <strong>Administrador de temas</strong> para:</p>
<ul>
<li>Elegir estructura: clásica, sidebar o ancho completo</li>
<li>Personalizar colores, tipografía y logos</li>
<li>Configurar header y footer</li>
<li>Gestionar áreas de widgets</li>
</ul>

<h2>Sistema de menús</h2>
<p>MuseDock CMS soporta menús en múltiples ubicaciones (navegación, footer, sidebar). Crea menús desde <strong>Apariencia → Menús</strong> y añade páginas, categorías o enlaces personalizados.</p>',
        'seo_title' => 'Primeros pasos con MuseDock CMS - Tutorial básico',
        'seo_description' => 'Tutorial de inicio rápido con MuseDock CMS. Crea páginas, configura temas, menús y publica tu primer contenido.',
        'seo_keywords' => 'tutorial, primeros pasos, CMS, páginas, temas, menús, configuración',
    ],
    [
        'title' => 'Sistema de blog y categorías',
        'slug' => 'sistema-blog-categorias-musedock',
        'excerpt' => 'Documentación del sistema de blog integrado: categorías jerárquicas, etiquetas, SEO automático y plantillas.',
        'content' => '<h2>El módulo Blog</h2>
<p>MuseDock CMS incluye un sistema de blog completo como módulo integrado. Características principales:</p>
<ul>
<li>Categorías jerárquicas (subcategorías ilimitadas)</li>
<li>Etiquetas (tags) para organización transversal</li>
<li>SEO automático: meta tags, Open Graph, Twitter Cards</li>
<li>URLs amigables configurables (prefijo de blog personalizable)</li>
<li>Editor enriquecido con shortcodes</li>
<li>Sistema de revisiones</li>
<li>Comentarios (opcionales)</li>
<li>Soporte multiidioma</li>
</ul>

<h2>Categorías</h2>
<p>Las categorías soportan estructura jerárquica con parent_id. Cada categoría tiene:</p>
<ul>
<li>Nombre y slug (URL)</li>
<li>Descripción y color</li>
<li>Imagen de cabecera</li>
<li>SEO propio (título, descripción, keywords)</li>
</ul>

<h2>Plantillas de blog</h2>
<p>Tres layouts disponibles:</p>
<ul>
<li><strong>Sidebar derecha</strong> (predeterminada)</li>
<li><strong>Sidebar izquierda</strong></li>
<li><strong>Ancho completo</strong></li>
</ul>

<h2>Shortcodes</h2>
<p>El editor soporta shortcodes para elementos dinámicos como sliders, galerías y formularios. Los shortcodes se procesan automáticamente al renderizar el contenido.</p>',
        'seo_title' => 'Sistema de blog en MuseDock CMS - Documentación',
        'seo_description' => 'Documentación del sistema de blog integrado en MuseDock CMS. Categorías, etiquetas, SEO, plantillas y shortcodes.',
        'seo_keywords' => 'blog, categorías, tags, SEO, documentación, CMS',
    ],
    [
        'title' => 'Sistema de temas y plantillas',
        'slug' => 'temas-plantillas-musedock-cms',
        'excerpt' => 'Cómo crear, personalizar y gestionar temas en MuseDock CMS. Estructura de archivos, opciones y hooks.',
        'content' => '<h2>Estructura de un tema</h2>
<p>Los temas se ubican en <code>themes/{nombre-tema}/</code> y contienen:</p>
<ul>
<li><code>theme.json</code> - Configuración del tema (nombre, opciones, áreas de contenido)</li>
<li><code>views/</code> - Plantillas Blade</li>
<li><code>views/layouts/</code> - Layouts principales (app.blade.php)</li>
<li><code>views/partials/</code> - Partials reutilizables (header, footer, sidebar)</li>
</ul>

<h2>Plantillas de página</h2>
<p>Cada archivo .blade.php en <code>views/</code> es una plantilla disponible. Las plantillas especiales:</p>
<ul>
<li><code>page.blade.php</code> - Plantilla de página por defecto</li>
<li><code>home.blade.php</code> - Página de inicio</li>
<li><code>single.blade.php</code> - Artículo de blog</li>
<li><code>category.blade.php</code> - Archivo de categoría</li>
<li><code>product.blade.php</code> - Página de producto</li>
</ul>

<h2>Opciones del tema</h2>
<p>El archivo <code>theme.json</code> define opciones visualizables desde el admin. Soporta:</p>
<ul>
<li>Selección de colores, tipografía, logos</li>
<li>Toggle de componentes (topbar, footer, sidebar)</li>
<li>Estructura de página (clásica, sidebar, full-width)</li>
<li>Áreas de contenido con soporte para widgets</li>
</ul>

<h2>Sistema de widgets</h2>
<p>Los widgets se asignan a áreas definidas en el tema. El WidgetManager renderiza cada área según la configuración del tenant.</p>',
        'seo_title' => 'Temas y plantillas en MuseDock CMS - Guía para desarrolladores',
        'seo_description' => 'Guía para crear y personalizar temas en MuseDock CMS. Estructura de archivos, plantillas Blade, opciones y widgets.',
        'seo_keywords' => 'temas, plantillas, Blade, widgets, theme.json, personalización',
    ],
    [
        'title' => 'Módulos y plugins',
        'slug' => 'modulos-plugins-musedock-cms',
        'excerpt' => 'Cómo funcionan los módulos y plugins en MuseDock CMS. Estructura, creación y gestión de extensiones.',
        'content' => '<h2>Sistema de módulos</h2>
<p>MuseDock CMS usa un sistema modular donde cada funcionalidad es un módulo independiente:</p>
<ul>
<li><strong>Blog</strong> - Sistema de publicación de artículos</li>
<li><strong>Media Manager</strong> - Gestor de archivos multimedia</li>
<li><strong>AI Writer</strong> - Asistente de escritura con IA</li>
<li><strong>AI Image</strong> - Generación de imágenes con IA</li>
<li><strong>Custom Forms</strong> - Formularios personalizados</li>
<li><strong>Image Gallery</strong> - Galerías de imágenes</li>
<li><strong>React Sliders</strong> - Sliders interactivos</li>
<li><strong>Elements</strong> - Bloques de contenido reutilizable</li>
<li><strong>WP Importer</strong> - Importar desde WordPress</li>
<li><strong>Musedock Shop</strong> - Tienda online integrada</li>
</ul>

<h2>Estructura de un módulo</h2>
<p>Cada módulo en <code>modules/{slug}/</code> contiene:</p>
<ul>
<li><code>module.json</code> - Metadatos del módulo</li>
<li><code>bootstrap.php</code> - Registro e inicialización</li>
<li><code>routes.php</code> - Rutas del módulo</li>
<li><code>controllers/</code> - Controladores</li>
<li><code>models/</code> - Modelos de datos</li>
<li><code>views/</code> - Vistas Blade</li>
</ul>

<h2>Plugins</h2>
<p>Los plugins extienden funcionalidad a nivel de sistema. Se ubican en <code>plugins/</code> y pueden ser:</p>
<ul>
<li><strong>superadmin</strong> - Funcionalidades del panel de superadministrador</li>
<li><strong>tenant-shared</strong> - Compartidos entre tenants</li>
</ul>',
        'seo_title' => 'Módulos y plugins de MuseDock CMS - Documentación',
        'seo_description' => 'Documentación del sistema de módulos y plugins de MuseDock CMS. Lista de módulos, estructura y cómo crear extensiones.',
        'seo_keywords' => 'módulos, plugins, extensiones, blog, media, AI, tienda',
    ],
    [
        'title' => 'SEO y multi-idioma',
        'slug' => 'seo-multi-idioma-musedock-cms',
        'excerpt' => 'Sistema SEO integrado de MuseDock CMS: meta tags automáticos, Open Graph,canonical URLs y sistema de traducciones.',
        'content' => '<h2>SEO integrado</h2>
<p>MuseDock CMS incluye SEO avanzado sin necesidad de plugins:</p>
<ul>
<li><strong>Meta tags automáticos</strong> - title, description, keywords por página</li>
<li><strong>Open Graph</strong> - og:title, og:description, og:image, og:type</li>
<li><strong>Twitter Cards</strong> - Soporte completo para summary_large_image</li>
<li><strong>Canonical URLs</strong> - Evitar contenido duplicado</li>
<li><strong>Robots directive</strong> - Control de indexación por página</li>
<li><strong>Sitemap XML</strong> - Generación automática</li>
<li><strong>Schema.org</strong> - Datos estructurados JSON-LD</li>
</ul>

<h2>Configuración SEO por página</h2>
<p>Cada página y artículo tiene campos SEO dedicados:</p>
<ul>
<li>Título SEO (si se deja vacío, usa el título de la página)</li>
<li>Descripción meta (recomendado: 150-160 caracteres)</li>
<li>Keywords (separados por coma)</li>
<li>Imagen OG/Twitter (para compartir en redes sociales)</li>
<li>URL canónica</li>
<li>Dirección de robots (index/follow, noindex, etc.)</li>
</ul>

<h2>Sistema multi-idioma</h2>
<p>MuseDock CMS soporta múltiples idiomas con:</p>
<ul>
<li>Detección automática del idioma del navegador</li>
<li>Traducciones por página (tabla page_translations)</li>
<li>Traducciones por artículo (tabla blog_post_translations)</li>
<li>Forzar idioma desde configuración (force_lang)</li>
<li>Selector de idioma en el frontend</li>
<li>Idiomas activos por tenant (tabla languages)</li>
</ul>',
        'seo_title' => 'SEO y multi-idioma en MuseDock CMS - Guía completa',
        'seo_description' => 'Sistema SEO integrado y multi-idioma en MuseDock CMS. Meta tags, Open Graph, sitemap XML, traducciones de contenido.',
        'seo_keywords' => 'SEO, meta tags, Open Graph, multi-idioma, traducciones, sitemap',
    ],
];

// ===== PANEL DOCS =====
$panelDocs = [
    [
        'title' => 'Introducción a MuseDock Panel',
        'slug' => 'introduccion-musedock-panel',
        'excerpt' => 'MuseDock Panel: panel de administración de servidores web. Alternativa libre a cPanel y Plesk con 20 años de experiencia.',
        'content' => '<h2>¿Qué es MuseDock Panel?</h2>
<p>MuseDock Panel es un panel de administración de servidores web diseñado como alternativa libre y moderna a cPanel y Plesk. Desarrollado con 20 años de experiencia en administración de sistemas.</p>

<h2>Características principales</h2>
<ul>
<li><strong>Gestión de dominios</strong> - Crear, configurar y gestionar dominios y subdominios</li>
<li><strong>Servidor web Caddy</strong> - HTTPS automático con Let\'s Encrypt, HTTP/2 y HTTP/3</li>
<li><strong>Firewall integrado</strong> - Reglas de Firewall, Fail2Ban y protección DDoS</li>
<li><strong>Cluster y alta disponibilidad</strong> - Gestión de múltiples servidores</li>
<li><strong>DNS</strong> - Integración con Cloudflare DNS</li>
<li><strong>Monitorización</strong> - Estado de servicios, recursos y alertas</li>
<li><strong>PostgreSQL</strong> - Gestión de bases de datos</li>
<li><strong>Backup</strong> - Copias de seguridad automáticas</li>
</ul>

<h2>Stack tecnológico</h2>
<ul>
<li><strong>Go</strong> - Backend de alto rendimiento</li>
<li><strong>Caddy</strong> - Servidor web con HTTPS automático</li>
<li><strong>PostgreSQL</strong> - Base de datos principal</li>
<li><strong>Fail2Ban</strong> - Protección contra ataques de fuerza bruta</li>
</ul>

<h2>Licencia</h2>
<p>MuseDock Panel es <strong>Source Available (Provider Use)</strong>: puedes usarlo para operar tu hosting (incluido uso comercial con tus clientes), pero no para revender el panel como software/white-label SaaS. MuseDock Portal se licencia por separado.</p>',
        'seo_title' => 'MuseDock Panel - Introducción y características',
        'seo_description' => 'Introducción a MuseDock Panel, panel de administración de servidores. Alternativa libre a cPanel con Caddy, PostgreSQL, firewall y cluster.',
        'seo_keywords' => 'panel, hosting, servidor, cPanel, Caddy, PostgreSQL, firewall',
    ],
    [
        'title' => 'Gestión de dominios y SSL',
        'slug' => 'dominios-ssl-musedock-panel',
        'excerpt' => 'Cómo gestionar dominios, subdominios y certificados SSL en MuseDock Panel con Caddy y Let\'s Encrypt.',
        'content' => '<h2>Alta de dominios</h2>
<p>Desde el panel puedes dar de alta nuevos dominios:</p>
<ol>
<li>Accede a <strong>Dominios → Nuevo dominio</strong></li>
<li>Introduce el nombre del dominio</li>
<li>Selecciona el tipo de SSL (automático, Cloudflare, manual)</li>
<li>Configura el directorio raíz</li>
<li>El panel configura automáticamente Caddy y obtiene el certificado SSL</li>
</ol>

<h2>SSL automático</h2>
<p>MuseDock Panel usa Caddy como servidor web, que gestiona certificados SSL automáticamente:</p>
<ul>
<li><strong>Let\'s Encrypt</strong> - Renovación automática cada 60 días</li>
<li><strong>Cloudflare Origin</strong> - Certificados de origen para dominios tras Cloudflare</li>
<li><strong>Wildcards</strong> - Soporte para certificados wildcard con DNS Challenge</li>
</ul>

<h2>DNS</h2>
<p>Integración con Cloudflare DNS para:</p>
<ul>
<li>Crear registros A, AAAA, CNAME, MX, TXT automáticamente</li>
<li>Verificar propagación de DNS</li>
<li>Gestionar nameservers</li>
</ul>',
        'seo_title' => 'Gestión de dominios y SSL en MuseDock Panel',
        'seo_description' => 'Cómo gestionar dominios, SSL automático con Let\'s Encrypt y configuración DNS en MuseDock Panel.',
        'seo_keywords' => 'dominios, SSL, Let\'s Encrypt, Caddy, DNS, Cloudflare, HTTPS',
    ],
    [
        'title' => 'Firewall y seguridad',
        'slug' => 'firewall-seguridad-musedock-panel',
        'excerpt' => 'Sistema de seguridad de MuseDock Panel: firewall integrado, Fail2Ban, protección DDoS y buenas prácticas.',
        'content' => '<h2>Firewall integrado</h2>
<p>MuseDock Panel incluye un sistema de firewall a nivel de aplicación:</p>
<ul>
<li>Reglas de allow/deny por IP y puerto</li>
<li>Bloqueo automático de bots maliciosos</li>
<li>Rate limiting por dominio</li>
<li>Whitelist de IPs de confianza</li>
</ul>

<h2>Fail2Ban</h2>
<p>Integración completa con Fail2Ban:</p>
<ul>
<li>Protección contra fuerza bruta en SSH, HTTP y FTP</li>
<li>Reglas personalizables por servicio</li>
<li>Baneos temporales o permanentes</li>
<li>Monitor de IPs baneadas en tiempo real</li>
</ul>

<h2>Buenas prácticas de seguridad</h2>
<ul>
<li>Cambiar puerto SSH por defecto</li>
<li>Usar autenticación por clave pública</li>
<li>Mantener el sistema actualizado</li>
<li>Revisar logs periódicamente</li>
<li>Configurar alertas de seguridad</li>
</ul>',
        'seo_title' => 'Firewall y seguridad en MuseDock Panel - Guía',
        'seo_description' => 'Sistema de seguridad de MuseDock Panel: firewall, Fail2Ban, rate limiting y protección contra ataques.',
        'seo_keywords' => 'firewall, seguridad, Fail2Ban, DDoS, protección, servidor',
    ],
    [
        'title' => 'Cluster y alta disponibilidad',
        'slug' => 'cluster-alta-disponibilidad-musedock-panel',
        'excerpt' => 'Configuración de cluster, failover automático y alta disponibilidad con MuseDock Panel.',
        'content' => '<h2>Arquitectura de cluster</h2>
<p>MuseDock Panel soporta configuraciones de cluster para alta disponibilidad:</p>
<ul>
<li>Nodo maestro (master) - Gestión centralizada</li>
<li>Nodos trabajadores (workers) - Ejecutan los sitios</li>
<li>Replicación de configuración entre nodos</li>
<li>Sincronización de archivos estática</li>
</ul>

<h2>Failover automático</h2>
<p>El sistema detecta fallos en nodos y redirige tráfico automáticamente:</p>
<ol>
<li>Health checks periódicos a cada nodo</li>
<li>Detección de fallos en menos de 30 segundos</li>
<li>Migración automática de dominios a nodos sanos</li>
<li>Recuperación automática cuando el nodo vuelve a estar disponible</li>
</ol>

<h2>Monitorización</h2>
<p>El panel monitoriza en tiempo real:</p>
<ul>
<li>Uso de CPU, RAM y disco por nodo</li>
<li>Estado de servicios (Caddy, PostgreSQL, PHP)</li>
<li>Tráfico de red y ancho de banda</li>
<li>Alertas por email y notificaciones</li>
</ul>',
        'seo_title' => 'Cluster y alta disponibilidad con MuseDock Panel',
        'seo_description' => 'Configuración de cluster, failover automático y monitorización en MuseDock Panel para alta disponibilidad.',
        'seo_keywords' => 'cluster, alta disponibilidad, failover, monitorización, nodos',
    ],
    [
        'title' => 'Base de datos PostgreSQL',
        'slug' => 'postgresql-musedock-panel',
        'excerpt' => 'Gestión de bases de datos PostgreSQL en MuseDock Panel: creación, backups, usuarios y optimización.',
        'content' => '<h2>Gestión de PostgreSQL</h2>
<p>MuseDock Panel usa PostgreSQL como motor de base de datos principal:</p>
<ul>
<li>Creación de bases de datos desde el panel</li>
<li>Gestión de usuarios y permisos</li>
<li>phpPgAdmin integrado para administración</li>
<li>Consultas SQL directas desde el panel</li>
</ul>

<h2>Backups</h2>
<p>Sistema de copias de seguridad:</p>
<ul>
<li>Backups automáticos programados (cron)</li>
<li>Backup manual bajo demanda</li>
<li>Restauración selectiva por base de datos</li>
<li>Rotación automática de backups antiguos</li>
<li>Almacenamiento local o remoto (S3)</li>
</ul>

<h2>Optimización</h2>
<p>El panel incluye herramientas de optimización:</p>
<ul>
<li>Vacuum y analyze automático</li>
<li>Índices sugeridos basados en consultas lentas</li>
<li>Monitorización de conexiones activas</li>
<li>Configuración de pg_tune optimizada</li>
</ul>',
        'seo_title' => 'PostgreSQL en MuseDock Panel - Gestión y backups',
        'seo_description' => 'Gestión de bases de datos PostgreSQL en MuseDock Panel. Creación, backups, usuarios y optimización.',
        'seo_keywords' => 'PostgreSQL, base de datos, backup, optimización, phpPgAdmin',
    ],
    [
        'title' => 'Despliegue y configuración de Caddy',
        'slug' => 'caddy-despliegue-musedock-panel',
        'excerpt' => 'Configuración del servidor web Caddy en MuseDock Panel: HTTPS automático, virtual hosts y reescritura.',
        'content' => '<h2>Caddy como servidor web</h2>
<p>MuseDock Panel usa Caddy como servidor web por defecto:</p>
<ul>
<li>HTTPS automático con Let\'s Encrypt</li>
<li>HTTP/2 y HTTP/3 (QUIC) habilitados por defecto</li>
<li>Compresión Brotli y gzip automática</li>
<li>Reverse proxy integrado</li>
<li>Virtual hosts por dominio</li>
</ul>

<h2>Configuración de virtual hosts</h2>
<p>Cada dominio tiene su propia configuración Caddy generada automáticamente:</p>
<ul>
<li>Directorio raíz configurable</li>
<li>Índices personalizados (index.php, index.html)</li>
<li>Reglas de reescritura para CMS</li>
<li>PHP-FPM integrado</li>
</ul>

<h2>PHP</h2>
<p>Soporte multi-versión de PHP:</p>
<ul>
<li>PHP 8.1, 8.2, 8.3 disponibles</li>
<li>Selector de versión por dominio</li>
<li>Configuración de php.ini por dominio</li>
<li>OPcache y JIT configurables</li>
</ul>

<h2>Caché</h2>
<p>Caché HTTP integrada:</p>
<ul>
<li>Caché de archivos estáticos</li>
<li>Headers de caché configurables</li>
<li>Purga de caché desde el panel</li>
<li>Soporte para CDN (Cloudflare)</li>
</ul>',
        'seo_title' => 'Caddy y despliegue en MuseDock Panel - Configuración',
        'seo_description' => 'Configuración del servidor Caddy en MuseDock Panel. HTTPS automático, virtual hosts, PHP y caché.',
        'seo_keywords' => 'Caddy, HTTPS, Let\'s Encrypt, PHP, virtual host, caché, HTTP/2',
    ],
];

// ===== PORTAL DOCS =====
$portalDocs = [
    [
        'title' => 'MuseDock Portal - Próximamente',
        'slug' => 'musedock-portal-proximamente',
        'excerpt' => 'MuseDock Portal está en desarrollo activo. Portal de clientes para hosting con facturación Stripe y gestión de tenants.',
        'content' => '<h2>¿Qué es MuseDock Portal?</h2>
<p>MuseDock Portal es el portal de clientes integrado con MuseDock Panel. Permitirá a los usuarios de hosting gestionar sus servicios de forma autónoma.</p>

<h2>Características planificadas</h2>
<ul>
<li><strong>Facturación</strong> - Integración con Stripe para suscripciones y pagos</li>
<li><strong>Gestión de tenants</strong> - Los clientes gestionan sus sitios CMS</li>
<li><strong>Failover</strong> - Migración automática entre nodos del cluster</li>
<li><strong>Tickets de soporte</strong> - Sistema de soporte integrado</li>
<li><strong>Panel de control</strong> - Estadísticas, uso de recursos y facturación</li>
</ul>

<h2>Estado del desarrollo</h2>
<p>MuseDock Portal se encuentra en fase de desarrollo activo. Si estás interesado en participar en el testing o conocer fechas de lanzamiento, contacta con nosotros.</p>

<h2>Integración</h2>
<p>Portal se integra nativamente con:</p>
<ul>
<li>MuseDock Panel (gestión de infraestructura)</li>
<li>MuseDock CMS (gestión de contenido por tenants)</li>
<li>Stripe (procesamiento de pagos)</li>
</ul>',
        'seo_title' => 'MuseDock Portal - Próximamente - Portal de clientes de hosting',
        'seo_description' => 'MuseDock Portal: portal de clientes para hosting con facturación Stripe, gestión de tenants y failover automático. En desarrollo.',
        'seo_keywords' => 'portal, clientes, hosting, facturación, Stripe, próximamente',
    ],
    [
        'title' => 'Facturación y Stripe',
        'slug' => 'facturacion-stripe-musedock-portal',
        'excerpt' => 'Sistema de facturación con Stripe integrado en MuseDock Portal para gestión de suscripciones de hosting.',
        'content' => '<h2>Sistema de facturación</h2>
<p>MuseDock Portal integrará Stripe como plataforma de pagos:</p>
<ul>
<li>Suscripciones mensuales y anuales</li>
<li>Facturación automática recurrente</li>
<li>Múltiples planes de hosting</li>
<li>Pruebas gratuitas configurables</li>
<li>Gestión de cupones y descuentos</li>
</ul>

<h2>Planes de hosting</h2>
<p>Los planes se configuran desde el panel de superadministración:</p>
<ul>
<li>Definir recursos (espacio, ancho de banda, tenants)</li>
<li>Asignar precio y periodicidad</li>
<li>Configurar límites y cuotas</li>
</ul>

<p><em>Este artículo se actualizará conforme avance el desarrollo de MuseDock Portal.</em></p>',
        'seo_title' => 'Facturación Stripe en MuseDock Portal - Documentación',
        'seo_description' => 'Sistema de facturación con Stripe en MuseDock Portal. Suscripciones, planes de hosting y pagos recurrentes.',
        'seo_keywords' => 'facturación, Stripe, suscripciones, hosting, pagos',
    ],
    [
        'title' => 'Gestión de tenants desde Portal',
        'slug' => 'gestion-tenants-musedock-portal',
        'excerpt' => 'Cómo los clientes gestionarán sus tenants de MuseDock CMS desde el portal de usuarios.',
        'content' => '<h2>Gestión de tenants</h2>
<p>Desde MuseDock Portal, los clientes de hosting podrán:</p>
<ul>
<li><strong>Crear nuevos tenants</strong> - Dar de alta nuevos sitios CMS</li>
<li><strong>Gestionar dominios</strong> - Asociar dominios a sus tenants</li>
<li><strong>Configurar SSL</strong> - Activar HTTPS en sus sitios</li>
<li><strong>Monitorizar recursos</strong> - Ver uso de CPU, RAM y disco</li>
<li><strong>Gestionar backups</strong> - Crear y restaurar copias de seguridad</li>
</ul>

<h2>Integración con CMS</h2>
<p>Cada tenant tiene acceso completo al panel de administración de MuseDock CMS:</p>
<ul>
<li>Dashboard con estadísticas</li>
<li>Gestión de contenido (páginas, blog, media)</li>
<li>Personalización de temas</li>
<li>Configuración de módulos</li>
<li>Gestión de usuarios del tenant</li>
</ul>

<p><em>Este artículo se actualizará conforme avance el desarrollo de MuseDock Portal.</em></p>',
        'seo_title' => 'Gestión de tenants en MuseDock Portal - Documentación',
        'seo_description' => 'Gestión de tenants de MuseDock CMS desde el portal de clientes. Crear sitios, dominios, SSL y recursos.',
        'seo_keywords' => 'tenants, CMS, hosting, gestión, dominios, SSL',
    ],
];

/**
 * Insert a blog post and link it to a category
 */
function tenantWhere(string $column = 'tenant_id'): string
{
    global $targetTenantId;
    return $targetTenantId === null ? "{$column} IS NULL" : "{$column} = " . (int)$targetTenantId;
}

function ensureSlugForPost(int $postId, string $slug): void
{
    global $pdo, $driver, $targetTenantId;

    $whereTenant = tenantWhere('tenant_id');
    $stmt = $pdo->query("SELECT id FROM slugs WHERE module = 'blog' AND reference_id = " . (int)$postId . " AND {$whereTenant} LIMIT 1");
    $existingId = (int)($stmt->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $up = $pdo->prepare("UPDATE slugs SET slug = ?, prefix = ?, locale = NULL WHERE id = ?");
        $up->execute([$slug, 'docs', $existingId]);
        return;
    }

    if ($driver === 'mysql') {
        $ins = $pdo->prepare('INSERT INTO slugs (tenant_id, module, reference_id, slug, prefix, locale) VALUES (?, ?, ?, ?, ?, NULL)');
        $ins->execute([$targetTenantId, 'blog', $postId, $slug, 'docs']);
    } else {
        $ins = $pdo->prepare('INSERT INTO slugs (tenant_id, module, reference_id, slug, prefix, locale) VALUES (?, ?, ?, ?, ?, NULL)');
        $ins->execute([$targetTenantId, 'blog', $postId, $slug, 'docs']);
    }
}

function resolveOrCreateCategory(string $slug, string $name, ?int $parentId = null, int $order = 0): int
{
    global $pdo, $driver, $now, $targetTenantId;
    $whereTenant = tenantWhere('tenant_id');

    $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = ? AND {$whereTenant} LIMIT 1");
    $stmt->execute([$slug]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        $up = $pdo->prepare('UPDATE blog_categories SET name = ?, parent_id = ?, "order" = ?, updated_at = ? WHERE id = ?');
        $up->execute([$name, $parentId, $order, $now, $id]);
        return $id;
    }

    if ($driver === 'mysql') {
        $ins = $pdo->prepare('INSERT INTO blog_categories (tenant_id, parent_id, name, slug, description, `order`, post_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $ins->execute([$targetTenantId, $parentId, $name, $slug, '', $order, $now, $now]);
        return (int)$pdo->lastInsertId();
    }

    $ins = $pdo->prepare('INSERT INTO blog_categories (tenant_id, parent_id, name, slug, description, "order", post_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?) RETURNING id');
    $ins->execute([$targetTenantId, $parentId, $name, $slug, '', $order, $now, $now]);
    return (int)$ins->fetchColumn();
}

function insertDoc($post, $categoryId) {
    global $now, $targetTenantId, $pdo;
    
    // Check if already exists
    $postQ = Database::table('blog_posts')->where('slug', $post['slug']);
    if ($targetTenantId === null) {
        $postQ->whereNull('tenant_id');
    } else {
        $postQ->where('tenant_id', $targetTenantId);
    }
    $existing = $postQ->first();
    if ($existing) {
        $postId = (int)$existing->id;
        Database::table('blog_posts')->where('id', $postId)->update([
            'title' => $post['title'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'status' => 'published',
            'visibility' => 'public',
            'post_type' => 'docs',
            'seo_title' => $post['seo_title'],
            'seo_description' => $post['seo_description'],
            'seo_keywords' => $post['seo_keywords'],
            'robots_directive' => 'index,follow',
            'updated_at' => $now,
        ]);

        Database::table('blog_post_categories')->where('post_id', $postId)->delete();
        Database::table('blog_post_categories')->insert([
            'post_id' => $postId,
            'category_id' => $categoryId,
        ]);
        ensureSlugForPost($postId, $post['slug']);

        echo "  UPDATED: {$post['slug']} (ID: {$postId}, cat: {$categoryId})\n";
        return;
    }
    
    $data = [
        'tenant_id' => $targetTenantId,
        'user_id' => 1,
        'user_type' => 'superadmin',
        'title' => $post['title'],
        'slug' => $post['slug'],
        'excerpt' => $post['excerpt'],
        'content' => $post['content'],
        'status' => 'published',
        'visibility' => 'public',
        'base_locale' => 'es',
        'allow_comments' => 0,
        'published_at' => $now,
        'seo_title' => $post['seo_title'],
        'seo_description' => $post['seo_description'],
        'seo_keywords' => $post['seo_keywords'],
        'robots_directive' => 'index,follow',
        'created_at' => $now,
        'updated_at' => $now,
        'post_type' => 'docs',
    ];
    
    $id = Database::table('blog_posts')->insert($data);
    
    // Link to category
    Database::table('blog_post_categories')->insert([
        'post_id' => $id,
        'category_id' => $categoryId,
    ]);
    ensureSlugForPost((int)$id, $post['slug']);
    
    echo "  CREATED: {$post['slug']} (ID: {$id}, cat: {$categoryId})\n";
}

function findPostIdBySlug(string $slug): ?int
{
    global $targetTenantId;
    $q = Database::table('blog_posts')
        ->where('slug', $slug)
        ->where('post_type', 'docs')
        ->where('status', 'published');
    if ($targetTenantId === null) {
        $q->whereNull('tenant_id');
    } else {
        $q->where('tenant_id', $targetTenantId);
    }
    $row = $q->first();
    return $row && isset($row->id) ? (int)$row->id : null;
}

function setPrimaryCategoryByPostSlug(string $postSlug, int $categoryId): void
{
    global $now;
    $postId = findPostIdBySlug($postSlug);
    if (!$postId) {
        echo "  WARN: post not found for category remap: {$postSlug}\n";
        return;
    }

    Database::table('blog_post_categories')->where('post_id', $postId)->delete();
    Database::table('blog_post_categories')->insert([
        'post_id' => $postId,
        'category_id' => $categoryId,
        'created_at' => $now,
    ]);
    echo "  REMAP: {$postSlug} -> cat {$categoryId}\n";
}

/**
 * Resolve (or create) docs product category in current scope.
 */
function resolveDocsProductCategory(string $slug, string $name, int $order): int
{
    $docsRootId = resolveOrCreateCategory('docs', 'Documentación', null, 0);
    return resolveOrCreateCategory($slug, $name, $docsRootId, $order);
}

$cmsCategoryId = resolveDocsProductCategory('cms', 'MuseDock CMS', 1);
$panelCategoryId = resolveDocsProductCategory('panel', 'MuseDock Panel', 2);
$portalCategoryId = resolveDocsProductCategory('portal', 'MuseDock Portal', 3);

echo "=== Seeding CMS Docs (category {$cmsCategoryId}) ===\n";
foreach ($cmsDocs as $doc) {
    insertDoc($doc, $cmsCategoryId);
}

echo "\n=== Seeding Panel Docs (category {$panelCategoryId}) ===\n";
foreach ($panelDocs as $doc) {
    insertDoc($doc, $panelCategoryId);
}

echo "\n=== Seeding Portal Docs (category {$portalCategoryId}) ===\n";
foreach ($portalDocs as $doc) {
    insertDoc($doc, $portalCategoryId);
}

echo "\n=== Structuring entry sections per product ===\n";
// Keep one explicit landing section per product to make docs cards deterministic.
$cmsGettingStartedId = resolveOrCreateCategory('getting-started', 'Presentación', $cmsCategoryId, 1);
$panelGettingStartedId = resolveOrCreateCategory('panel-getting-started', 'Presentación', $panelCategoryId, 0);
$portalGettingStartedId = resolveOrCreateCategory('portal-getting-started', 'Presentación', $portalCategoryId, 0);

// Normalize CMS section labels in Spanish and ensure they exist.
$cmsConfigId = resolveOrCreateCategory('configuration', 'Configuración', $cmsCategoryId, 2);
$cmsThemesId = resolveOrCreateCategory('themes', 'Temas', $cmsCategoryId, 3);
$cmsModulesId = resolveOrCreateCategory('modules-plugins', 'Módulos y Plugins', $cmsCategoryId, 4);
$cmsLegalId = resolveOrCreateCategory('legal-templates', 'Plantillas Legales', $cmsCategoryId, 5);
$cmsApiId = resolveOrCreateCategory('api-rest', 'API REST', $cmsCategoryId, 6);
$cmsMultiTenancyId = resolveOrCreateCategory('multi-tenancy', 'Multi-tenancy', $cmsCategoryId, 7);
$cmsDeploymentId = resolveOrCreateCategory('deployment', 'Despliegue', $cmsCategoryId, 8);

// Panel sections (avoid loose root posts under product).
$panelDnsCertId = resolveOrCreateCategory('certificados-dns', 'DNS y Certificados', $panelCategoryId, 1);
$panelDomainsId = resolveOrCreateCategory('panel-dominios-ssl', 'Dominios y SSL', $panelCategoryId, 2);
$panelCaddyId = resolveOrCreateCategory('panel-caddy-despliegue', 'Caddy y Despliegue', $panelCategoryId, 3);
$panelSecurityId = resolveOrCreateCategory('panel-seguridad', 'Seguridad', $panelCategoryId, 4);
$panelClusterId = resolveOrCreateCategory('panel-cluster-ha', 'Cluster y Alta Disponibilidad', $panelCategoryId, 5);
$panelDbId = resolveOrCreateCategory('panel-postgresql', 'Base de Datos', $panelCategoryId, 6);

// Portal sections.
$portalBillingId = resolveOrCreateCategory('portal-facturacion', 'Facturación y Pagos', $portalCategoryId, 1);
$portalTenantsId = resolveOrCreateCategory('portal-tenants', 'Tenants y Sitios', $portalCategoryId, 2);

// Ensure each product has a clear entry article in its landing section.
setPrimaryCategoryByPostSlug('sistema-de-documentacion', $cmsGettingStartedId);
setPrimaryCategoryByPostSlug('introduccion-musedock-panel', $panelGettingStartedId);
setPrimaryCategoryByPostSlug('musedock-portal-proximamente', $portalGettingStartedId);

// CMS article organization.
setPrimaryCategoryByPostSlug('introduccion-musedock-cms', $cmsGettingStartedId);
setPrimaryCategoryByPostSlug('primeros-pasos-musedock-cms', $cmsGettingStartedId);
setPrimaryCategoryByPostSlug('instalacion-musedock-cms', $cmsDeploymentId);
setPrimaryCategoryByPostSlug('temas-plantillas-musedock-cms', $cmsThemesId);
setPrimaryCategoryByPostSlug('modulos-plugins-musedock-cms', $cmsModulesId);
setPrimaryCategoryByPostSlug('sistema-blog-categorias-musedock', $cmsModulesId);
setPrimaryCategoryByPostSlug('seo-multi-idioma-musedock-cms', $cmsConfigId);
setPrimaryCategoryByPostSlug('por-que-tu-cms-deberia-generar-paginas-legales-automaticamente', $cmsLegalId);

// Panel article organization.
setPrimaryCategoryByPostSlug('dns-multiproveedor-certificados-panel', $panelDnsCertId);
setPrimaryCategoryByPostSlug('dominios-ssl-musedock-panel', $panelDomainsId);
setPrimaryCategoryByPostSlug('caddy-despliegue-musedock-panel', $panelCaddyId);
setPrimaryCategoryByPostSlug('firewall-seguridad-musedock-panel', $panelSecurityId);
setPrimaryCategoryByPostSlug('cluster-alta-disponibilidad-musedock-panel', $panelClusterId);
setPrimaryCategoryByPostSlug('postgresql-musedock-panel', $panelDbId);

// Portal article organization.
setPrimaryCategoryByPostSlug('facturacion-stripe-musedock-portal', $portalBillingId);
setPrimaryCategoryByPostSlug('gestion-tenants-musedock-portal', $portalTenantsId);

echo "\nDone! Docs seeded successfully.\n";
