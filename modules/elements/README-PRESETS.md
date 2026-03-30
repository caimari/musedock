# Elements Module - Style Presets

El módulo Elements incluye un sistema flexible de presets de diseño que permite personalizar la apariencia de los elementos (Heroes, FAQs, CTAs) sin modificar el código.

## Presets Disponibles

### 1. **Default** (`default.css`)
- Diseño equilibrado con gradientes modernos
- Colores: Morados y azules (#667eea, #764ba2)
- Ideal para: Sitios corporativos y profesionales
- Estilo: Moderno pero conservador

### 2. **Modern** (`modern.css`)
- Diseño ultra-moderno con bordes muy redondeados
- Colores: Púrpura vibrante (#6366f1, #8b5cf6)
- Ideal para: Startups, tecnología, SaaS
- Estilo: Audaz y contemporáneo
- Características: Sombras pronunciadas, efectos 3D, tipografía bold

### 3. **Minimal** (`minimal.css`)
- Diseño minimalista con espacios amplios
- Colores: Blanco y negro con grises neutros
- Ideal para: Portfolios, agencias creativas, fotografía
- Estilo: Limpio y elegante
- Características: Sin bordes redondeados, tipografía ligera, mucho espacio en blanco

### 4. **Creative** (`creative.css`)
- Diseño creativo con colores vibrantes
- Colores: Gradientes multicolor (#ff6b6b, #feca57, #48dbfb)
- Ideal para: Marcas juveniles, e-commerce, eventos
- Estilo: Divertido y dinámico
- Características: Formas únicas, sombras offset, rotaciones ligeras

## Cómo Usar los Presets

### Opción 1: A Nivel de Tema (Recomendado)

Define el preset en el archivo `functions.php` de tu tema:

```php
// Método 1: Usando constante
define('THEME_ELEMENTS_PRESET', 'modern');

// Método 2: Usando función
function get_theme_elements_preset() {
    return 'minimal';
}
```

### Opción 2: Override Completo por Tema

Crea un archivo CSS de sobrescritura en tu tema:

**Ubicación:** `themes/tu-tema/assets/css/elements-override.css`

Este archivo se cargará después del preset base y puede sobrescribir cualquier estilo:

```css
/* Ejemplo: Cambiar el color del hero */
.element-hero.layout-image-right {
    background: linear-gradient(135deg, #FF0000 0%, #00FF00 100%);
}

/* Cambiar el botón */
.hero-btn {
    background: #333;
    color: #fff;
    border-radius: 10px;
}
```

### Opción 3: Configuración Global del Módulo

Puedes configurar un preset por defecto para todo el sistema usando la base de datos:

```php
// En tu código de inicialización o settings
\Elements\Models\ElementSetting::set('style_preset', 'creative');
```

## Orden de Prioridad

El sistema carga los estilos en este orden de prioridad:

1. **Función del tema** (`get_theme_elements_preset()`) - Máxima prioridad
2. **Constante del tema** (`THEME_ELEMENTS_PRESET`)
3. **Configuración del módulo** (base de datos)
4. **Default** - Si no hay nada configurado

Luego, si existe `themes/tu-tema/assets/css/elements-override.css`, se carga adicionalmentecon mayor especificidad.

## Ubicación de los Archivos CSS

Todos los presets están en:
```
/public/assets/modules/elements/css/
├── default.css
├── modern.css
├── minimal.css
└── creative.css
```

## Crear un Preset Personalizado

1. Copia uno de los presets existentes:
```bash
cp /public/assets/modules/elements/css/default.css /public/assets/modules/elements/css/mi-preset.css
```

2. Edita `mi-preset.css` con tus estilos personalizados

3. Actualiza el archivo `bootstrap.php` del módulo para incluir tu preset en la lista de disponibles:
```php
$availablePresets = ['default', 'modern', 'minimal', 'creative', 'mi-preset'];
```

4. Actívalo en tu tema:
```php
define('THEME_ELEMENTS_PRESET', 'mi-preset');
```

## Ejemplos de Uso por Tema

### Tema Corporativo
```php
// themes/corporate/functions.php
define('THEME_ELEMENTS_PRESET', 'minimal');
```

### Tema E-commerce
```php
// themes/shop/functions.php
define('THEME_ELEMENTS_PRESET', 'creative');
```

### Tema Portfolio
```php
// themes/portfolio/functions.php
define('THEME_ELEMENTS_PRESET', 'minimal');

// Y además un override para ajustes finos
// Crea: themes/portfolio/assets/css/elements-override.css
```

## JavaScript

El archivo JavaScript (`elements.js`) es común para todos los presets y maneja la funcionalidad interactiva (acordeón de FAQs, etc.). No necesitas modificarlo a menos que quieras cambiar el comportamiento.

## Soporte

Para más información, consulta la documentación completa del módulo Elements.
