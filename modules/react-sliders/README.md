# React Sliders Module

Sistema moderno de gestión de sliders/carousels con React y Tailwind CSS para MuseDock CMS.

## 🎯 Características

- ✅ **Sliders modernos** con React y Tailwind CSS
- ✅ **Multi-motor**: Swiper, Slick, Keen Slider, Embla
- ✅ **Multi-tenant**: Sliders globales y por tenant
- ✅ **Drag & Drop**: Reordenación visual de diapositivas
- ✅ **Traducciones**: Español e Inglés incluidos
- ✅ **Responsive**: Adaptado a todos los dispositivos
- ✅ **Personalización**: Colores, overlays, animaciones
- ✅ **Botones CTA**: Call-to-action por diapositiva

## 📦 Instalación

### ⚡ Instalación Automática con Migraciones (Recomendado)

El módulo usa el **sistema de migraciones automáticas estilo Laravel** de MuseDock.

**Pasos:**

1. **Activar el módulo** en el panel de superadmin:
   - Ve a `/musedock/modules`
   - Busca "React Sliders"
   - Haz clic en "Activar"
   - ✨ **Las tablas se crearán automáticamente**

**¿Qué hace el sistema?**
- Detecta que el módulo tiene migraciones pendientes
- Ejecuta `2025_11_18_000000_create_react_sliders_tables.php` automáticamente
- Crea las tablas `react_sliders` y `react_slides`
- Registra la migración en la tabla `migrations` para evitar duplicados
- Todo en una transacción segura ✅

### 🔧 Método Alternativo: Instalación Manual con SQL

Si prefieres instalar manualmente:

```bash
mysql -u usuario -p nombre_bd < modules/react-sliders/install.sql
```

O copiar y pegar el contenido de `install.sql` en phpMyAdmin.

### 📦 Instalar Dependencias Frontend

```bash
npm install swiper react react-dom sortablejs
# O con yarn
yarn add swiper react react-dom sortablejs
```

## 🚀 Uso

### 📍 URLs del Panel

**Superadmin (Sliders Globales):**
- Listado: `https://musedock.net/musedock/react-sliders`
- Crear: `https://musedock.net/musedock/react-sliders/create`
- Editar: `https://musedock.net/musedock/react-sliders/{id}/edit`

**Tenant (Sliders del Sitio):**
- Listado: `https://musedock.net/admin/react-sliders`
- Crear: `https://musedock.net/admin/react-sliders/create`
- Editar: `https://musedock.net/admin/react-sliders/{id}/edit`

### En tus Plantillas Blade

```blade
{{-- Renderizar un slider por identificador --}}
{!! render_react_slider('hero-slider') !!}

{{-- O obtener el slider y hacer algo personalizado --}}
@php
    $slider = get_react_slider('hero-slider', tenant_id());
    $slides = $slider ? $slider->slides() : [];
@endphp

@if($slider)
    <div id="my-custom-slider">
        @foreach($slides as $slide)
            <div class="slide">
                <img src="{{ $slide->image_url }}" alt="{{ $slide->title }}">
                <h2>{{ $slide->title }}</h2>
            </div>
        @endforeach
    </div>
@endif
```

### En el Contenido (Shortcodes tipo WordPress)

Los shortcodes funcionan automáticamente en **páginas** y **posts del blog** sin configuración adicional.

Puedes insertar sliders directamente en el contenido:

```html
<h1>Mi Página</h1>

[react-slider identifier="hero"]

<p>Contenido de la página...</p>

[react-slider id=1]
```

✅ **Integración automática:** Los shortcodes se procesan automáticamente cuando se muestra el contenido de:
- Páginas (PageController)
- Posts del blog (a través de `process_shortcodes()`)
- Cualquier contenido que use la función `process_shortcodes()`

**Ver documentación completa de integración:** `INTEGRATION.md`

### Configuración de un Slider

```php
$settings = [
    'engine' => 'swiper',           // Motor: swiper, slick, keen, embla
    'animation' => 'slide',         // fade, slide, cube, coverflow, flip
    'autoplay' => true,
    'autoplay_delay' => 5000,       // ms
    'loop' => true,
    'navigation' => true,           // Flechas prev/next
    'pagination' => true,           // Dots
    'slides_per_view' => 1,
    'space_between' => 0,           // px entre slides
    'speed' => 500                  // ms de transición
];
```

## 📁 Estructura del Módulo

```
react-sliders/
├── module.json              # Configuración del módulo
├── bootstrap.php            # Inicialización
├── routes.php               # Rutas (superadmin y tenant)
├── helpers.php              # Funciones helper
├── README.md                # Este archivo
│
├── lang/                    # Traducciones
│   ├── es.json             # Español
│   └── en.json             # Inglés
│
├── models/                  # Modelos
│   ├── ReactSlider.php
│   └── ReactSlide.php
│
├── controllers/             # Controladores
│   ├── Superadmin/
│   │   ├── ReactSliderController.php
│   │   └── ReactSlideController.php
│   └── Tenant/
│       ├── ReactSliderController.php
│       └── ReactSlideController.php
│
├── views/                   # Vistas Blade
│   ├── superadmin/
│   │   └── sliders/
│   │       ├── index.blade.php
│   │       ├── create.blade.php
│   │       └── edit.blade.php
│   ├── tenant/
│   │   └── sliders/
│   │       └── ... (similar)
│   └── components/
│       └── Slider.jsx       # Componente React
│
├── assets/                  # Assets
│   ├── js/
│   │   └── ReactSlider.js
│   └── css/
│       └── slider.css
│
└── migrations/              # Migraciones
    └── 2025_11_18_000000_create_react_sliders_tables.php
```

## 🔐 Permisos

El módulo define los siguientes permisos:

- `react_sliders.manage` - Gestión completa
- `react_sliders.view` - Ver sliders
- `react_sliders.create` - Crear sliders
- `react_sliders.edit` - Editar sliders
- `react_sliders.delete` - Eliminar sliders

## 🎨 Personalización de Diapositivas

Cada diapositiva puede tener:

- **Imagen**: URL de la imagen de fondo
- **Título, Subtítulo, Descripción**: Textos overlay
- **Botón CTA**: Texto, enlace y target
- **Colores**: Background y texto personalizados
- **Overlay**: Opacidad del overlay oscuro (0.0 - 1.0)
- **Custom CSS**: CSS adicional por slide
- **Custom Data**: JSON con datos personalizados

## 📝 Funciones Helper

### `__rs($key, $replace = [])`
Traducción del módulo

```php
echo __rs('slider.title'); // "Sliders" o "Sliders"
echo __rs('messages.slider_created'); // "Slider creado correctamente"
```

### `get_react_slider($identifier, $tenantId = null)`
Obtener un slider por identificador

```php
$slider = get_react_slider('hero-slider', tenant_id());
```

### `render_react_slider($identifier, $options = [])`
Renderizar un slider completo

```php
echo render_react_slider('hero-slider');
```

## 🔄 API del Componente React

```jsx
<ReactSlider
  slides={[
    {
      id: 1,
      title: "Título",
      subtitle: "Subtítulo",
      description: "Descripción",
      image: "/uploads/slide.jpg",
      button: {
        text: "Ver más",
        link: "/about",
        target: "_self"
      },
      styles: {
        backgroundColor: "#000",
        color: "#fff",
        overlayOpacity: 0.3
      }
    }
  ]}
  settings={{
    engine: "swiper",
    autoplay: true,
    autoplay_delay: 5000,
    loop: true,
    navigation: true,
    pagination: true
  }}
/>
```

## 🐛 Troubleshooting

### El slider no se muestra

1. Verifica que el módulo esté activo en la BD
2. Verifica que el slider tenga `is_active = 1`
3. Verifica que haya diapositivas activas
4. Revisa la consola del navegador por errores JS

### Drag & drop no funciona

1. Verifica que Sortable.js esté cargado
2. Revisa la ruta del asset en la vista

### Traducciones no funcionan

1. Verifica que los archivos `lang/es.json` y `lang/en.json` existan
2. Verifica que `app_locale()` devuelva 'es' o 'en'

## 📄 Licencia

Este módulo es parte de MuseDock CMS.

## 👨‍💻 Autor

MuseDock Development Team

## 🔖 Versión

1.0.0 - Noviembre 2025
