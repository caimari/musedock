# MuseDock Theme (Zipprich Adaptation)

Un tema moderno para MuseDock CMS adaptado del tema Zipprich, diseñado específicamente para sitios de hosting web con soporte completo multi-tenant.

## Características Principales

### 🌐 Multi-Tenant Support
- Soporte completo para múltiples inquilinos
- Configuración de sitios independiente para cada tenant
- Traducciones por tenant (español/inglés)
- Assets separados para evitar conflictos

### 🎨 Personalización Completa
- **Colores Dinámicos**: Personalización de todos los colores del tema
- **Header Configurable**: Logo, menús, CTA buttons, sticky header
- **Top Bar Opcional**: Información de contacto, redes sociales
- **Footer Flexible**: 4 columnas de widgets, formulario de contacto
- **Slider Principal**: 3 slides con auto-play y transiciones

### 📱 Diseño Responsivo
- Mobile-first design
- Navegación táctil optimizada
- Menús colapsables para dispositivos móviles
- Formularios adaptativos

### 🔧 Integraciones CMS
- **Site Settings**: Logo, favicon, colores, SEO
- **Redes Sociales**: Facebook, Twitter, Instagram, LinkedIn, YouTube, Pinterest, TikTok
- **Custom Fields**: Banners, sidebars, metadatos
- **SEO Completo**: Meta tags, Open Graph, Twitter Cards
- **Blog System**: Categorías, tags, author info

## Estructura de Archivos

```
themes/musedock/
├── theme.json                    # Configuración del tema
├── README.md                     # Documentación
└── views/
    ├── layouts/
    │   └── app.blade.php         # Layout principal
    ├── partials/
    │   ├── header.blade.php       # Header con navegación
    │   └── footer.blade.php       # Footer con widgets
    ├── home.blade.php             # Página de inicio con slider
    ├── page.blade.php             # Páginas dinámicas
    └── blog/                     # Vistas del blog
        ├── index.blade.php         # Archivo del blog
        ├── single.blade.php        # Post individual
        ├── category.blade.php      # Categorías
        └── tag.blade.php          # Tags

public/assets/themes/musedock/
├── css/                         # Hojas de estilo
├── js/                          # JavaScript
├── fonts/                       # Fuentes
└── images/                      # Imágenes del tema
```

## Configuración del Tema

### Opciones Principales en theme.json:

#### 🎨 Colores
- Color primario: `#ff5e15`
- Color secundario: `#1a2a40`
- Colores de header, footer, links
- Variables CSS dinámicas

#### 📋 Header
- Background del header
- Color del logo y textos
- Sticky header opcional
- Botón CTA configurable
- Selector de idiomas

#### 📱 Top Bar
- Activar/desactivar
- Colores personalizados
- Mostrar teléfono, email, WhatsApp
- Información de contacto

#### 🦶 Footer
- 4 columnas de widgets
- Formulario de contacto AJAX
- Redes sociales
- Copyright dinámico

#### 🎠 Slider
- Auto-play configurable
- Intervalo de transición
- Efectos (fade/slide)
- Navegación táctil

## Funciones Multi-Tenant

El tema incluye funciones específicas para multi-tenancy:

```php
// Detección del tenant actual
$_tenantId = tenant_id();
$_isTenant = $_tenantId !== null;

// Settings del sitio por tenant
site_setting('site_name', 'default');
translatable_site_setting('footer_text', $lang, 'default');

// Opciones del tema por tenant
themeOption('header.header_bg_color', '#ffffff');

// Menús multi-tenant
@custommenu('nav', null, ['nav_class' => 'main-menu']);
```

## Custom Fields Disponibles

### Páginas
- `banner_image`: Imagen de banner
- `show_sidebar`: Mostrar sidebar
- `sidebar_position`: Posición (left/right)
- `show_meta`: Mostrar metadatos
- `category`: Categoría de la página
- `tags`: Tags separados por comas
- `author`: Autor de la página
- `featured_image`: Imagen destacada

### Posts del Blog
- `featured_image`: Imagen destacada
- `category`: Categoría del post
- `tags`: Tags del post
- `author`: Autor del post
- Opciones de visualización (author, date, category, tags)

## Widgets Areas

- `sidebar_left`: Sidebar izquierdo
- `sidebar_right`: Sidebar derecho
- `footer_column_1`: Columna 1 del footer
- `footer_column_2`: Columna 2 del footer
- `footer_column_3`: Columna 3 del footer
- `footer_column_4`: Columna 4 del footer

## Posiciones de Menú

- `header`: Menú principal de navegación (soporta mega menús)
- `footer`: Menú secundario en el footer

## Soporte de Idiomas

- **Idiomas soportados**: Español (es), Inglés (en)
- **Detección automática**: Basada en URL o parámetro GET
- **Traducciones**: Configuradas por tenant
- **Textos del tema**: Función `__()` para traducciones

## Integración de Redes Sociales

Configuración de redes sociales a través de site settings:

```php
site_setting('social_facebook');
site_setting('social_twitter');
site_setting('social_instagram');
site_setting('social_linkedin');
site_setting('social_youtube');
site_setting('social_pinterest');
site_setting('social_tiktok');
```

## SEO y Meta Tags

El tema incluye soporte completo para SEO:

- Meta title y description dinámicos
- Open Graph (Facebook, LinkedIn)
- Twitter Cards
- Favicon dinámico
- Keywords y author
- RSS Feed

## Shortcodes Disponibles

- `[slider]` - Slider principal
- `[pricing_table]` - Tabla de precios
- `[testimonial]` - Testimonios
- `[feature_box]` - Cajas de características
- `[call_to_action]` - Botones CTA
- `[contact_form]` - Formulario de contacto

## Custom Post Types

- **Testimonials**: Gestión de testimonios
- **Pricing Plans**: Planes de precios

## Instalación

1. Copiar la carpeta `musedock` a `themes/`
2. Los assets ya están en `public/assets/themes/musedock/`
3. Configurar como tema activo en el panel de administración
4. Personalizar opciones en Theme Options

## Personalización

### CSS Variables
El tema usa variables CSS dinámicas:

```css
:root {
  --topbar-bg-color: {{ themeOption('topbar.topbar_bg_color', '#1a2a40') }};
  --header-bg-color: {{ themeOption('header.header_bg_color', '#f8f9fa') }};
  --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#f8fafe') }};
  /* ... más variables */
}
```

### JavaScript
- jQuery 3.x
- Bootstrap 4
- Owl Carousel
- Custom slider
- Validación de formularios

## Compatibilidad

- **PHP**: >= 8.0
- **MuseDock CMS**: >= 1.0.0
- **Navegadores**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Dispositivos**: Mobile, Tablet, Desktop

## Licencia

MIT License - Libre para uso comercial y personal

## Soporte

Para soporte técnico o reportar issues:
- GitHub Repository: [MuseDock/musedock-theme]
- Documentation: https://musedock.net/docs
- Support Email: support@musedock.net

---

**Desarrollado por MuseDock CMS**  
*Tema moderno multi-tenant para sitios de hosting web*
