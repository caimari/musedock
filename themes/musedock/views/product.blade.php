@extends('layouts.app')

@php
  $page = $page ?? null;
  $slug = $page->slug ?? '';

  // Product configurations
  $products = [
    'cms' => [
      'badge' => 'MIT License · Self-Hostable · Gratis',
      'badge_color' => '#28a745',
      'hero_title' => 'Tu web en segundos. Sin instalar nada.',
      'hero_subtitle' => 'O instálalo en tu servidor con licencia MIT. Un CMS moderno con IA, SEO automático y sistema de documentación integrado. Construido sobre 20 años de experiencia real.',
      'cta_primary' => 'Crear mi sitio gratis',
      'cta_primary_url' => '/register',
      'cta_secondary' => 'Self-Hosting (GitHub)',
      'cta_secondary_url' => 'https://github.com/caimari/musedock',
      'repo_url' => 'https://github.com/caimari/musedock',
      'docs_url' => '/blog/category/cms',
      'install_cmd' => "git clone https://github.com/caimari/musedock.git\ncomposer install\nphp muse install",
      'license_label' => 'MIT',
      'version_note' => 'Licencia MIT — Abril 2026',
      'tagline' => 'El CMS libre que necesitas. Como WordPress, pero moderno.',
      'features' => [
        ['icon' => 'fa-magic', 'title' => 'Blog con IA integrada', 'desc' => 'Genera artículos e imágenes con IA (OpenAI, Claude, Gemini). Asistente de escritura integrado en el editor.'],
        ['icon' => 'fa-search', 'title' => 'SEO automático', 'desc' => 'Meta tags, JSON-LD (Article, FAQPage, BreadcrumbList), Open Graph, canonical URLs, sitemap XML y RSS.'],
        ['icon' => 'fa-users', 'title' => 'Multi-tenant nativo', 'desc' => 'Un solo CMS, múltiples sitios. Panel de administración por tenant y superadmin central. Aislamiento completo de datos.'],
        ['icon' => 'fa-paint-brush', 'title' => '6 temas + editor visual', 'desc' => 'Themes: default, musedock, HighTechIT, play-bootstrap, react-modern. Personalización de colores, widgets y layouts.'],
        ['icon' => 'fa-book', 'title' => 'Sistema de docs integrado', 'desc' => 'Documentación tipo GitBook con sidebar, TOC, navegación prev/next y búsqueda. Organizada por producto y sección.'],
        ['icon' => 'fa-puzzle-piece', 'title' => '11 módulos + plugins', 'desc' => 'Blog, AI Writer, AI Image, Custom Forms, Elements, Image Gallery, Instagram Gallery, Media Manager, Shop, React Sliders, WP Importer.'],
        ['icon' => 'fa-language', 'title' => 'Multi-idioma', 'desc' => 'Español, inglés y francés. Traducciones de posts con detección automática de idioma del visitante.'],
        ['icon' => 'fa-lock', 'title' => 'SSL y seguridad', 'desc' => 'SSL gratuito con Caddy + Cloudflare DNS. Roles y permisos granulares, 2FA, protección XSS/CSRF, rate limiting.'],
        ['icon' => 'fa-ticket', 'title' => 'Soporte integrado', 'desc' => 'Sistema de tickets por tenant, notificaciones, gestión de usuarios con roles personalizados.'],
      ],
      'specs' => [
        'PHP 8.1+ / MySQL 5.7+ / PostgreSQL',
        'Servidor web: Caddy 2.x o Nginx/Apache',
        '11 módulos oficiales incluidos',
        '6 temas listos para usar',
        'Multi-idioma: ES, EN, FR',
        'API REST + hooks/filtros extensibles',
        'Licencia MIT — uso comercial libre',
      ],
      'faq' => [
        ['q' => '¿MuseDock CMS es realmente gratis?', 'a' => 'Sí. MuseDock CMS tiene licencia MIT, una de las licencias de código abierto más permisivas. Puedes usarlo, modificarlo y distribuirlo libremente, incluso con fines comerciales.'],
        ['q' => '¿Puedo instalarlo en mi propio servidor?', 'a' => 'Por supuesto. Descarga el código desde GitHub, ejecuta composer install y configura tu .env. Funciona con Caddy, Nginx o Apache en cualquier hosting con PHP 8.1+.'],
        ['q' => '¿Qué proveedores de IA soporta?', 'a' => 'OpenAI (GPT-4, GPT-3.5), Anthropic (Claude) y Google (Gemini). Configura tu API key desde el panel de administración y empieza a generar contenido.'],
        ['q' => '¿Es adecuado para hosting providers?', 'a' => 'Sí. Su arquitectura multi-tenant permite ofrecer hosting con CMS preinstalado a tus clientes, cada uno con su panel independiente y aislamiento de datos completo.'],
        ['q' => '¿Puedo migrar desde WordPress?', 'a' => 'Sí. MuseDock CMS incluye un módulo de importación desde WordPress que migra posts, categorías, etiquetas e imágenes automáticamente.'],
      ],
    ],
    'panel' => [
      'badge' => 'Source Available · Gratis uso personal · v1.0.183',
      'badge_color' => '#4e73df',
      'hero_title' => 'Administra tu infraestructura sin licencias caras',
      'hero_subtitle' => 'La alternativa libre a cPanel y Plesk. Panel de administración de servidores con firewall, monitorización, cluster y automatización. 20 años de experiencia en cada línea de código.',
      'cta_primary' => 'Instalar ahora',
      'cta_primary_url' => 'https://github.com/caimari/musedock-panel',
      'cta_secondary' => 'Ver documentación',
      'cta_secondary_url' => '/blog/category/panel',
      'repo_url' => 'https://github.com/caimari/musedock-panel',
      'docs_url' => '/blog/category/panel',
      'install_cmd' => "git clone https://github.com/caimari/musedock-panel.git /opt/musedock-panel\nbash /opt/musedock-panel/install.sh",
      'license_label' => 'Source Available',
      'version_note' => 'v1.0.183 — Abril 2026',
      'tagline' => 'Para los que están cansados de pagar licencias de cPanel y Plesk.',
      'features' => [
        ['icon' => 'fa-server', 'title' => 'Provisioning de hosting', 'desc' => 'Usuarios Linux, vhosts, PHP-FPM multi-versión (8.1–8.3), rutas Caddy automáticas. Crea cuentas de hosting en segundos.'],
        ['icon' => 'fa-shield', 'title' => 'Firewall avanzado', 'desc' => 'Auditoría de reglas, snapshots completos, export/import JSON, presets predefinidos. Recuperación instantánea de configuraciones.'],
        ['icon' => 'fa-ban', 'title' => 'Fail2Ban integrado', 'desc' => 'Gestión visual de jails, bans y whitelist. Acciones rápidas (ban/unban por IP), export/import JSON para mover configuraciones entre nodos.'],
        ['icon' => 'fa-heartbeat', 'title' => 'Monitor y Health Score', 'desc' => 'Dashboard con health score en tiempo real, alertas configurables, sistema anti-spam, análisis de ruido y warm-up automático del collector.'],
        ['icon' => 'fa-sitemap', 'title' => 'Cluster y replicación', 'desc' => 'Sincronización de archivos con lsyncd, watchdog de degradación, acciones de recuperación automática, federación entre nodos.'],
        ['icon' => 'fa-lock', 'title' => 'Seguridad MFA + Hardening', 'desc' => 'MFA TOTP, auditoría de hardening, detección de drift, análisis de exposición de puertos, detección de anomalías en login.'],
        ['icon' => 'fa-globe', 'title' => 'Dominios, DNS y SSL', 'desc' => 'Cloudflare DNS integrado, SSL/TLS automático con ACME, gestión de dominios por cuenta de hosting.'],
        ['icon' => 'fa-database', 'title' => 'Bases de datos', 'desc' => 'PostgreSQL nativo para el panel. MySQL/MariaDB opcional para aplicaciones de clientes. Gestión completa desde la interfaz.'],
        ['icon' => 'fa-refresh', 'title' => 'Actualizaciones web + shell', 'desc' => 'Actualiza desde la interfaz web o por terminal con git pull + update.sh. Warm-up automático del monitor post-actualización.'],
        ['icon' => 'fa-cloud', 'title' => 'WireGuard VPN', 'desc' => 'Gestión de túneles WireGuard para comunicación segura entre nodos del cluster o acceso remoto.'],
        ['icon' => 'fa-envelope', 'title' => 'Mail', 'desc' => 'Gestión de cuentas de correo electrónico por dominio, integrada con el provisioning de hosting.'],
        ['icon' => 'fa-file-text-o', 'title' => 'Backups y File Manager', 'desc' => 'Sistema de backups integrado, file manager con exploración de archivos, logs de actividad y auditoría.'],
      ],
      'specs' => [
        'Ubuntu 22.04+ / Debian 12+',
        'PHP 8.2 / 8.3',
        'PostgreSQL (panel) + MySQL/MariaDB (clientes)',
        'Caddy 2.x (web server integrado)',
        'Firewall con export/import JSON',
        'Fail2Ban con gestión visual de jails',
        'Cluster con lsyncd + watchdog',
        'MFA TOTP + Hardening audit',
        '33 módulos/controladores activos',
      ],
      'faq' => [
        ['q' => '¿Es MuseDock Panel gratis?', 'a' => 'Es gratis para uso personal (proyectos propios, aprendizaje, servidores caseros). Para uso comercial (ofrecer hosting a clientes de pago), se requiere licencia comercial. Una fracción de lo que cuesta cPanel o Plesk.'],
        ['q' => '¿En qué se diferencia de cPanel/Plesk?', 'a' => 'MuseDock Panel usa Caddy en lugar de Apache/Nginx, PostgreSQL en lugar de MySQL para el core, tiene firewall y Fail2Ban integrados, monitorización con health score, y cluster nativo. Es más moderno, más ligero y sin licencias mensuales caras.'],
        ['q' => '¿Qué experiencia hay detrás?', 'a' => 'Más de 20 años como administrador de servidores y empresa de telecomunicaciones. Cada feature está diseñada desde la experiencia real operando en producción, no desde la teoría.'],
        ['q' => '¿Puedo migrar desde otro panel?', 'a' => 'Puedes crear las cuentas de hosting desde cero. El sistema de export/import JSON del firewall, Fail2Ban y Cron facilita transferir configuraciones. Estamos trabajando en herramientas de migración automatizada.'],
        ['q' => '¿Soporta múltiples nodos?', 'a' => 'Sí. El sistema de cluster permite sincronizar archivos entre nodos con lsyncd, monitorizar la salud de cada nodo, y ejecutar actualizaciones en batch por SSH.'],
      ],
    ],
    'portal' => [
      'badge' => 'Próximamente · Módulo Pro',
      'badge_color' => '#8b5cf6',
      'hero_title' => 'El portal que tus clientes de hosting merecen',
      'hero_subtitle' => 'Portal de clientes integrado con MuseDock Panel. Facturación con Stripe, gestión de tenants, failover automático y control de licencias. Próximamente disponible.',
      'cta_primary' => 'Notificarme cuando esté disponible',
      'cta_primary_url' => 'mailto:info@musedock.com?subject=Beta%20MuseDock%20Portal',
      'cta_secondary' => 'Conocer MuseDock Panel',
      'cta_secondary_url' => '/panel',
      'repo_url' => '#',
      'docs_url' => '/blog/category/portal',
      'install_cmd' => "# Próximamente — en desarrollo activo",
      'license_label' => 'Módulo Pro',
      'version_note' => 'En desarrollo — Abril 2026',
      'tagline' => 'El puente entre tu infraestructura y tus clientes.',
      'features' => [
        ['icon' => 'fa-credit-card', 'title' => 'Facturación con Stripe', 'desc' => 'Pagos recurrentes, facturas automáticas, múltiples monedas. Integración nativa con Stripe sin plugins de terceros.'],
        ['icon' => 'fa-users', 'title' => 'Gestión de tenants', 'desc' => 'Registro de clientes, aprovisionamiento automático de cuentas de hosting, ciclo de vida completo del cliente.'],
        ['icon' => 'fa-exchange', 'title' => 'Failover automático', 'desc' => 'Detección de caídas y migración automática de servicios entre nodos del cluster.'],
        ['icon' => 'fa-key', 'title' => 'Gestión de licencias', 'desc' => 'Control de licencias para MuseDock Panel y módulos Pro. Activación y desactivación remota.'],
        ['icon' => 'fa-dashboard', 'title' => 'Dashboard del cliente', 'desc' => 'Panel de control donde el cliente gestiona sus servicios, ve facturas, abre tickets y monitoriza sus recursos.'],
        ['icon' => 'fa-plug', 'title' => 'Integración con MuseDock Panel', 'desc' => 'Comunicación directa con MuseDock Panel para aprovisionar servicios, gestionar dominios y sincronizar estados.'],
      ],
      'specs' => [
        'Integración nativa con MuseDock Panel',
        'Facturación con Stripe',
        'Gestión completa del ciclo de vida del cliente',
        'Failover automático entre nodos',
        'Dashboard de cliente intuitivo',
        'Sistema de licencias integrado',
        'En desarrollo activo',
      ],
      'faq' => [
        ['q' => '¿Cuándo estará disponible MuseDock Portal?', 'a' => 'Estamos en desarrollo activo. Si quieres ser beta-tester o recibir notificaciones, escríbenos a info@musedock.com.'],
        ['q' => '¿Será de pago?', 'a' => 'Será un módulo Pro con licenciamiento específico. MuseDock Panel seguirá siendo gratis para uso personal.'],
        ['q' => '¿Puedo usar MuseDock Panel sin el Portal?', 'a' => 'Sí, son productos independientes. El Panel funciona perfectamente sin el Portal. El Portal añade la capa de cliente para hosting providers.'],
        ['q' => '¿Será compatible con otros paneles?', 'a' => 'El diseño inicial es para MuseDock Panel, pero la arquitectura modular permite futuras integraciones con otros sistemas.'],
      ],
    ],
  ];

  $product = $products[$slug] ?? $products['cms'];
@endphp

@section('title') 
{{ ($translation->seo_title ?: $translation->title ?: $product['hero_title']) . ' | ' . site_setting('site_name', '') }}
@endsection

@section('keywords') 
{{ $translation->seo_keywords ?? '' }}
@endsection

@section('description') 
{{ $translation->seo_description ?? $product['hero_subtitle'] }}
@endsection

@section('og_title') 
{{ $translation->seo_title ?: $translation->title ?: $product['hero_title'] }}
@endsection

@section('og_description') 
{{ $translation->seo_description ?? $product['hero_subtitle'] }}
@endsection

@section('twitter_title') 
{{ $translation->seo_title ?: $translation->title ?: $product['hero_title'] }}
@endsection

@section('twitter_description') 
{{ $translation->seo_description ?? $product['hero_subtitle'] }}
@endsection

@section('content')
<main>
  {{-- HERO SECTION --}}
  <section class="md-product-hero">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-7">
          <div class="md-product-badge" style="background: {{ $product['badge_color'] }}20; color: {{ $product['badge_color'] }}; border: 1px solid {{ $product['badge_color'] }}40;">
            {{ $product['badge'] }}
          </div>
          <h1 class="md-product-hero-title">{{ $product['hero_title'] }}</h1>
          <p class="md-product-hero-subtitle">{{ $product['hero_subtitle'] }}</p>
          <div class="md-product-cta-group">
            <a href="{{ $product['cta_primary_url'] }}" class="md-btn md-btn-primary">{{ $product['cta_primary'] }}</a>
            @if($product['cta_secondary_url'] !== '#')
            <a href="{{ $product['cta_secondary_url'] }}" class="md-btn md-btn-secondary" @if(str_starts_with($product['cta_secondary_url'], 'http')) target="_blank" @endif>{{ $product['cta_secondary'] }}</a>
            @endif
          </div>
          <p class="md-product-version">{{ $product['version_note'] }}</p>
        </div>
        <div class="col-md-5">
          <div class="md-product-hero-visual">
            @if($slug === 'cms')
              <div class="md-product-icon-grid">
                <div class="md-icon-box"><i class="fa fa-magic"></i><span>IA</span></div>
                <div class="md-icon-box"><i class="fa fa-search"></i><span>SEO</span></div>
                <div class="md-icon-box"><i class="fa fa-users"></i><span>Multi-tenant</span></div>
                <div class="md-icon-box"><i class="fa fa-paint-brush"></i><span>Temas</span></div>
                <div class="md-icon-box"><i class="fa fa-book"></i><span>Docs</span></div>
                <div class="md-icon-box"><i class="fa fa-puzzle-piece"></i><span>Módulos</span></div>
                <div class="md-icon-box"><i class="fa fa-language"></i><span>i18n</span></div>
                <div class="md-icon-box"><i class="fa fa-lock"></i><span>SSL</span></div>
                <div class="md-icon-box"><i class="fa fa-ticket"></i><span>Soporte</span></div>
              </div>
            @elseif($slug === 'panel')
              <div class="md-product-icon-grid">
                <div class="md-icon-box"><i class="fa fa-server"></i><span>Hosting</span></div>
                <div class="md-icon-box"><i class="fa fa-shield"></i><span>Firewall</span></div>
                <div class="md-icon-box"><i class="fa fa-ban"></i><span>Fail2Ban</span></div>
                <div class="md-icon-box"><i class="fa fa-heartbeat"></i><span>Monitor</span></div>
                <div class="md-icon-box"><i class="fa fa-sitemap"></i><span>Cluster</span></div>
                <div class="md-icon-box"><i class="fa fa-lock"></i><span>MFA</span></div>
                <div class="md-icon-box"><i class="fa fa-globe"></i><span>DNS</span></div>
                <div class="md-icon-box"><i class="fa fa-database"></i><span>BD</span></div>
                <div class="md-icon-box"><i class="fa fa-cloud"></i><span>WireGuard</span></div>
              </div>
            @else
              <div class="md-product-icon-grid">
                <div class="md-icon-box"><i class="fa fa-credit-card"></i><span>Billing</span></div>
                <div class="md-icon-box"><i class="fa fa-users"></i><span>Tenants</span></div>
                <div class="md-icon-box"><i class="fa fa-exchange"></i><span>Failover</span></div>
                <div class="md-icon-box"><i class="fa fa-key"></i><span>Licencias</span></div>
                <div class="md-icon-box"><i class="fa fa-dashboard"></i><span>Dashboard</span></div>
                <div class="md-icon-box"><i class="fa fa-plug"></i><span>API</span></div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- EXPERIENCE BAR --}}
  <section class="md-experience-bar">
    <div class="container">
      <div class="md-experience-content">
        <div class="md-experience-years"><strong>20+</strong> años</div>
        <div class="md-experience-text">de experiencia en administración de servidores y telecomunicaciones, cristalizados en código moderno</div>
        <div class="md-experience-badges">
          <span class="md-exp-badge">Licencia {{ $product['license_label'] }}</span>
          <span class="md-exp-badge">PHP {{ $slug === 'panel' ? '8.2/8.3' : '8.1+' }}</span>
          <span class="md-exp-badge">{{ $slug === 'panel' ? 'PostgreSQL' : 'MySQL/PostgreSQL' }}</span>
          <span class="md-exp-badge">Caddy 2.x</span>
        </div>
      </div>
    </div>
  </section>

  {{-- FEATURES SECTION --}}
  <section class="md-product-features">
    <div class="container">
      <div class="text-center" style="margin-bottom:50px;">
        <h2 class="md-section-title">Funcionalidades</h2>
        <p class="md-section-subtitle">{{ $product['tagline'] }}</p>
      </div>
      <div class="row">
        @foreach($product['features'] as $i => $feature)
        <div class="col-md-{{ count($product['features']) > 6 ? '4' : '4' }} col-sm-6">
          <div class="md-feature-card">
            <div class="md-feature-icon" style="background: {{ $product['badge_color'] }}15; color: {{ $product['badge_color'] }};">
              <i class="fa {{ $feature['icon'] }}"></i>
            </div>
            <h3>{{ $feature['title'] }}</h3>
            <p>{{ $feature['desc'] }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- SPECS SECTION --}}
  <section class="md-product-specs">
    <div class="container">
      <div class="row">
        <div class="col-md-5">
          <h2 class="md-section-title text-left">Especificaciones técnicas</h2>
          <p class="md-specs-intro">Requisitos y capacidades reales del producto, extraídos directamente del código fuente.</p>
        </div>
        <div class="col-md-7">
          <ul class="md-specs-list">
            @foreach($product['specs'] as $spec)
            <li>
              <svg width="18" height="18" viewBox="0 0 16 16" fill="{{ $product['badge_color'] }}"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
              {{ $spec }}
            </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </section>

  {{-- SELF-HOSTING SECTION --}}
  @if($slug !== 'portal')
  <section class="md-product-install">
    <div class="container">
      <div class="text-center" style="margin-bottom:40px;">
        <h2 class="md-section-title">Instala en tu servidor</h2>
        <p class="md-section-subtitle">{{ $slug === 'cms' ? 'Self-hosting con licencia MIT. Tu CMS, tu servidor, tu control.' : 'Descarga y ejecuta. Funciona en Ubuntu 22.04+ y Debian 12+.' }}</p>
      </div>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <div class="md-install-box">
            <div class="md-install-header">
              <div class="md-install-dots">
                <span style="background:#ff5f57;"></span>
                <span style="background:#ffbd2e;"></span>
                <span style="background:#28c840;"></span>
              </div>
              <span class="md-install-title">Terminal</span>
              <button class="md-copy-btn" onclick="navigator.clipboard.writeText(this.closest('.md-install-box').querySelector('code').textContent)">Copiar</button>
            </div>
            <div class="md-install-body">
              <code>{{ $product['install_cmd'] }}</code>
            </div>
          </div>
          <div class="text-center" style="margin-top:24px;">
            <a href="{{ $product['repo_url'] }}" target="_blank" class="md-btn md-btn-secondary" style="display:inline-flex;align-items:center;gap:8px;">
              <i class="fa fa-github"></i> Ver en GitHub
            </a>
            <a href="{{ $product['docs_url'] }}" class="md-btn md-btn-secondary" style="display:inline-flex;align-items:center;gap:8px;margin-left:12px;">
              <i class="fa fa-book"></i> Documentación
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- PAGE CONTENT (from CMS editor) --}}
  @if(!empty($translation->content ?? null) && strip_tags($translation->content) !== '')
  <section class="md-product-extra-content">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-10">
          <div class="md-extra-content-inner">
            {!! apply_filters('the_content', $translation->content) !!}
          </div>
        </div>
      </div>
    </div>
  </section>
  @endif

  {{-- FAQ SECTION --}}
  <section class="md-product-faq">
    <div class="container">
      <div class="text-center" style="margin-bottom:40px;">
        <h2 class="md-section-title">Preguntas frecuentes</h2>
      </div>
      <div class="row justify-content-center">
        <div class="col-md-8">
          @foreach($product['faq'] as $i => $item)
          <div class="md-faq-item">
            <div class="md-faq-question" onclick="this.parentElement.classList.toggle('md-faq-open')">
              <h3>{{ $item['q'] }}</h3>
              <i class="fa fa-chevron-down"></i>
            </div>
            <div class="md-faq-answer">
              <p>{{ $item['a'] }}</p>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  {{-- CTA FINAL --}}
  <section class="md-product-cta-final">
    <div class="container text-center">
      <h2>{{ $slug === 'cms' ? 'Empieza hoy con MuseDock CMS' : ($slug === 'panel' ? 'Instala MuseDock Panel en tu servidor' : '¿Quieres ser de los primeros en probar el Portal?') }}</h2>
      <p>{{ $slug === 'cms' ? 'Crea tu sitio gratis o instala en tu servidor con licencia MIT.' : ($slug === 'panel' ? 'Libérate de las licencias mensuales de cPanel y Plesk.' : 'Escríbenos y te avisaremos cuando MuseDock Portal esté listo.') }}</p>
      <div style="margin-top:30px;">
        <a href="{{ $product['cta_primary_url'] }}" class="md-btn md-btn-primary md-btn-lg">{{ $product['cta_primary'] }}</a>
        @if($slug === 'cms')
        <a href="/register" class="md-btn md-btn-secondary md-btn-lg" style="margin-left:12px;">Ver planes de hosting</a>
        @elseif($slug === 'panel')
        <a href="/blog/category/panel" class="md-btn md-btn-secondary md-btn-lg" style="margin-left:12px;">Leer documentación</a>
        @endif
      </div>
    </div>
  </section>

  {{-- JSON-LD for Product + FAQPage + BreadcrumbList --}}
  @php
    $siteUrl = url('/');
    $pageUrl = $siteUrl . '/' . $slug;
    $productName = 'MuseDock ' . ucfirst($slug);
    $productDesc = $product['hero_subtitle'];
    
    $faqEntries = [];
    foreach($product['faq'] as $item) {
      $faqEntries[] = ['@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']]];
    }
    
    $jsonLd = [
      '@context' => 'https://schema.org',
      '@graph' => [
        [
          '@type' => 'Product',
          'name' => $productName,
          'description' => strip_tags($productDesc),
          'url' => $pageUrl,
          'brand' => ['@type' => 'Brand', 'name' => 'MuseDock'],
          'manufacturer' => ['@type' => 'Organization', 'name' => 'MuseDock', 'url' => $siteUrl],
          'category' => $slug === 'cms' ? 'Content Management System' : ($slug === 'panel' ? 'Server Management Software' : 'Client Portal Software'),
          'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock'],
        ],
        [
          '@type' => 'FAQPage',
          'mainEntity' => $faqEntries,
        ],
        [
          '@type' => 'BreadcrumbList',
          'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $siteUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $productName, 'item' => $pageUrl],
          ],
        ],
      ],
    ];
  @endphp
  <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</main>
@endsection

{{-- Product Page Styles --}}
@push('styles')
<style>
/* ===== HERO ===== */
.md-product-hero {
  padding: 80px 0 60px;
  background: linear-gradient(135deg, #f0f4ff 0%, #e8edf8 50%, #f5f0ff 100%);
  position: relative;
  overflow: hidden;
}
.md-product-hero::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(78,115,223,0.06) 0%, transparent 70%);
  pointer-events: none;
}
.md-product-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 24px;
  letter-spacing: 0.3px;
}
.md-product-hero-title {
  font-size: 2.8rem;
  font-weight: 800;
  color: #1a2a40;
  line-height: 1.2;
  margin-bottom: 20px;
}
.md-product-hero-subtitle {
  font-size: 1.15rem;
  color: #5a6577;
  line-height: 1.7;
  margin-bottom: 32px;
  max-width: 540px;
}
.md-product-cta-group { display: flex; gap: 12px; flex-wrap: wrap; }
.md-product-version { margin-top: 16px; font-size: 0.8rem; color: #8a94a6; }

/* Icon grid visual */
.md-product-icon-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 30px;
}
.md-icon-box {
  background: #fff;
  border-radius: 12px;
  padding: 20px 12px;
  text-align: center;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  transition: transform 0.2s;
}
.md-icon-box:hover { transform: translateY(-3px); }
.md-icon-box i { font-size: 1.8rem; color: #4e73df; display: block; margin-bottom: 8px; }
.md-icon-box span { font-size: 0.78rem; color: #5a6577; font-weight: 500; }

/* ===== BUTTONS ===== */
.md-btn {
  display: inline-flex;
  align-items: center;
  padding: 12px 28px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.95rem;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
  border: 2px solid transparent;
}
.md-btn-primary {
  background: #4e73df;
  color: #fff;
  border-color: #4e73df;
}
.md-btn-primary:hover { background: #3d5fcc; color: #fff; }
.md-btn-secondary {
  background: #fff;
  color: #4e73df;
  border-color: #4e73df;
}
.md-btn-secondary:hover { background: #f0f4ff; color: #4e73df; }
.md-btn-lg { padding: 14px 36px; font-size: 1.05rem; }

/* ===== EXPERIENCE BAR ===== */
.md-experience-bar {
  background: #1a2a40;
  padding: 30px 0;
}
.md-experience-content {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
  justify-content: center;
}
.md-experience-years {
  font-size: 2rem;
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
}
.md-experience-years strong { color: #4e73df; font-size: 2.4rem; }
.md-experience-text { color: #a0aec0; font-size: 1rem; max-width: 380px; }
.md-experience-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.md-exp-badge {
  background: rgba(78,115,223,0.15);
  color: #a0b4ff;
  padding: 5px 14px;
  border-radius: 16px;
  font-size: 0.78rem;
  font-weight: 500;
}

/* ===== FEATURES ===== */
.md-product-features {
  padding: 80px 0;
  background: #fff;
}
.md-section-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1a2a40;
  margin-bottom: 12px;
}
.md-section-subtitle {
  font-size: 1.05rem;
  color: #6c757d;
}
.md-feature-card {
  padding: 28px 24px;
  border-radius: 12px;
  border: 1px solid #e8edf5;
  margin-bottom: 24px;
  transition: transform 0.2s, box-shadow 0.2s;
  background: #fff;
  height: 100%;
}
.md-feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}
.md-feature-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  margin-bottom: 16px;
}
.md-feature-card h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1a2a40;
  margin-bottom: 8px;
}
.md-feature-card p {
  font-size: 0.9rem;
  color: #6c757d;
  line-height: 1.6;
  margin: 0;
}

/* ===== SPECS ===== */
.md-product-specs {
  padding: 70px 0;
  background: #f8fafd;
}
.md-specs-intro { color: #6c757d; font-size: 1rem; margin-bottom: 20px; }
.md-specs-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.md-specs-list li {
  display: flex;
  align-items: center;
  padding: 10px 0;
  font-size: 0.95rem;
  color: #4a5568;
  border-bottom: 1px solid #edf0f5;
}
.md-specs-list li:last-child { border-bottom: none; }
.md-specs-list li svg { margin-right: 12px; flex-shrink: 0; }

/* ===== INSTALL ===== */
.md-product-install {
  padding: 80px 0;
  background: #fff;
}
.md-install-box {
  border-radius: 12px;
  overflow: hidden;
  background: #1a2a40;
  box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.md-install-header {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: #2d3a4f;
  gap: 12px;
}
.md-install-dots { display: flex; gap: 6px; }
.md-install-dots span { width: 12px; height: 12px; border-radius: 50%; display: block; }
.md-install-title { color: #8a94a6; font-size: 0.8rem; flex: 1; }
.md-copy-btn {
  background: rgba(255,255,255,0.1);
  color: #a0aec0;
  border: none;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: background 0.2s;
}
.md-copy-btn:hover { background: rgba(255,255,255,0.2); color: #fff; }
.md-install-body { padding: 24px; }
.md-install-body code {
  display: block;
  color: #a0e8af;
  font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
  font-size: 0.9rem;
  line-height: 1.8;
  white-space: pre-wrap;
  word-break: break-all;
}

/* ===== EXTRA CONTENT ===== */
.md-product-extra-content {
  padding: 60px 0;
  background: #f8fafd;
}
.md-extra-content-inner {
  background: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  line-height: 1.8;
}

/* ===== FAQ ===== */
.md-product-faq {
  padding: 80px 0;
  background: #fff;
}
.md-faq-item {
  border: 1px solid #e8edf5;
  border-radius: 10px;
  margin-bottom: 12px;
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.md-faq-item:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.md-faq-question {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  cursor: pointer;
  user-select: none;
}
.md-faq-question h3 {
  font-size: 1rem;
  font-weight: 600;
  color: #1a2a40;
  margin: 0;
}
.md-faq-question i {
  color: #8a94a6;
  transition: transform 0.3s;
  font-size: 0.85rem;
}
.md-faq-open .md-faq-question i { transform: rotate(180deg); }
.md-faq-answer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease;
  padding: 0 24px;
}
.md-faq-open .md-faq-answer {
  max-height: 500px;
  padding: 0 24px 18px;
}
.md-faq-answer p {
  color: #5a6577;
  font-size: 0.95rem;
  line-height: 1.7;
  margin: 0;
}

/* ===== CTA FINAL ===== */
.md-product-cta-final {
  padding: 80px 0;
  background: linear-gradient(135deg, #1a2a40 0%, #2d3a5f 100%);
  color: #fff;
}
.md-product-cta-final h2 {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 16px;
}
.md-product-cta-final p {
  color: #a0aec0;
  font-size: 1.1rem;
  margin-bottom: 0;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .md-product-hero { padding: 50px 0 40px; }
  .md-product-hero-title { font-size: 1.8rem; }
  .md-product-hero-subtitle { font-size: 1rem; }
  .md-product-cta-group { flex-direction: column; }
  .md-btn { text-align: center; justify-content: center; }
  .md-experience-content { flex-direction: column; text-align: center; }
  .md-experience-years { font-size: 1.6rem; }
  .md-section-title { font-size: 1.5rem; }
  .md-product-cta-final h2 { font-size: 1.4rem; }
  .md-product-icon-grid { padding: 16px; gap: 10px; }
  .md-icon-box { padding: 14px 8px; }
  .md-icon-box i { font-size: 1.3rem; }
  .md-install-body code { font-size: 0.78rem; }
}
</style>
@endpush
</task_progress>
</write_to_file>
