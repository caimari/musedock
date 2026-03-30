# React Modern Theme para MuseDock CMS

Tema moderno y responsive construido con **React**, **TypeScript** y **Tailwind CSS**, completamente integrado con el sistema dinámico de MuseDock.

## 🚀 Características

- ✅ **React 18** con TypeScript para un código robusto y mantenible
- ✅ **Tailwind CSS 3** para estilos modernos y responsivos
- ✅ **Vite** para compilación ultra-rápida
- ✅ **Integración completa** con el sistema de menús dinámicos de MuseDock
- ✅ **Widgets y áreas de contenido** desde la base de datos
- ✅ **Multi-idioma** con selector de idiomas
- ✅ **SEO optimizado** con meta tags dinámicas
- ✅ **Animaciones suaves** con Tailwind y CSS
- ✅ **Responsive** para todos los dispositivos
- ✅ **Header sticky** con efectos al scroll
- ✅ **Redes sociales** configurables desde el panel
- ✅ **Personalizable** desde el panel de administración

## 📦 Instalación

### Requisitos Previos

- Node.js 18+ y npm/yarn/pnpm
- MuseDock CMS instalado y configurado
- Acceso al servidor para compilar assets

### Pasos de Instalación

1. **Navegar al directorio del tema:**
   ```bash
   cd /var/www/vhosts/musedock.net/httpdocs/themes/react-modern
   ```

2. **Instalar dependencias:**
   ```bash
   npm install
   # o
   yarn install
   # o
   pnpm install
   ```

3. **Compilar assets para producción:**
   ```bash
   npm run build
   ```

4. **Activar el tema desde el panel de MuseDock:**
   - Ve a `Temas` en el panel de administración
   - Selecciona "React Modern"
   - Haz clic en "Activar"

## 🛠️ Desarrollo

### Modo Desarrollo

Para desarrollo con recarga en caliente (hot reload):

```bash
npm run dev
```

Esto iniciará el servidor de desarrollo de Vite en `http://localhost:5173`.

**IMPORTANTE:** En modo desarrollo, necesitas cambiar temporalmente las rutas de los assets en `views/layouts/app.blade.php`:

```php
{{-- Desarrollo --}}
<script type="module" src="http://localhost:5173/src/main.tsx"></script>

{{-- Producción --}}
<script type="module" src="{{ asset('themes/react-modern/dist/main.js') }}"></script>
```

### Compilación para Producción

```bash
npm run build
```

Esto generará:
- `dist/main.js` - JavaScript compilado y minificado
- `dist/style.css` - Tailwind CSS compilado y optimizado
- `dist/chunks/*` - Code-splitting chunks para mejor rendimiento

### Watch Mode

Para compilar automáticamente cuando cambies archivos:

```bash
npm run watch
```

## 📁 Estructura del Proyecto

```
react-modern/
├── src/                      # Código fuente React/TypeScript
│   ├── components/          # Componentes React
│   │   ├── Header.tsx      # Cabecera con menú dinámico
│   │   └── Footer.tsx      # Pie de página
│   ├── hooks/              # Custom hooks
│   │   ├── useScrollPosition.ts
│   │   └── useMediaQuery.ts
│   ├── types/              # Tipos TypeScript
│   │   └── index.ts
│   ├── utils/              # Utilidades
│   │   └── index.ts
│   ├── styles/             # Estilos CSS
│   │   └── index.css       # Tailwind + estilos custom
│   └── main.tsx            # Entry point React
├── views/                   # Vistas Blade
│   ├── layouts/
│   │   └── app.blade.php   # Layout principal
│   ├── partials/
│   │   ├── footer-column.blade.php
│   │   └── widget-renderer.blade.php
│   ├── pages/
│   ├── home.blade.php      # Página de inicio
│   └── page.blade.php      # Plantilla de página
├── public/                  # Assets públicos
├── dist/                    # Assets compilados (generado)
├── theme.json              # Configuración del tema
├── package.json            # Dependencias Node
├── tsconfig.json           # Configuración TypeScript
├── vite.config.ts          # Configuración Vite
├── tailwind.config.js      # Configuración Tailwind
└── README.md              # Este archivo
```

## 🎨 Personalización

### Desde el Panel de Administración

El tema incluye opciones de personalización en el panel de MuseDock:

1. **Colores:**
   - Color primario
   - Color secundario
   - Color de acento

2. **Cabecera:**
   - Header transparente en inicio
   - Header fijo al scroll

3. **Código personalizado:**
   - CSS personalizado
   - JavaScript personalizado

### Modificación de Componentes React

Los componentes principales están en `src/components/`:

- **Header.tsx**: Navegación principal con menú dinámico
- **Footer.tsx**: Pie de página con redes sociales y contacto

### Modificación de Estilos

Los estilos están en `src/styles/index.css` usando Tailwind CSS.

Para añadir estilos personalizados, puedes:

1. **Usar clases de Tailwind** directamente en los componentes
2. **Extender Tailwind** en `tailwind.config.js`
3. **Añadir CSS custom** en `src/styles/index.css`

## 🔌 Integración con MuseDock

### Menús Dinámicos

El tema obtiene automáticamente los menús de la base de datos:

```php
// En app.blade.php, Blade obtiene el menú
$menuData = [
    'id' => 1,
    'title' => 'Menú Principal',
    'location' => 'nav',
    'items' => [...]
];

// Se pasa a React mediante data-attributes
<div data-menu='@json($menuData)'></div>
```

React consume estos datos en `Header.tsx`:

```typescript
interface HeaderProps {
  menu?: Menu;  // Tipado automáticamente
}
```

### Settings del Sistema

Todas las configuraciones de MuseDock están disponibles en React:

```typescript
settings.site_name
settings.site_logo
settings.social_facebook
settings.contact_email
// etc...
```

### Widgets

Los widgets se renderizan mediante Blade en las áreas definidas:

```blade
@include('partials.widget-renderer', ['areaSlug' => 'footer1'])
```

## 🌍 Multi-idioma

El tema soporta múltiples idiomas:

1. **Los idiomas activos** se obtienen de la tabla `languages`
2. **El selector de idiomas** está en el Footer
3. **Los menús y contenidos** se filtran por locale automáticamente

## 📱 Responsive Design

El tema es completamente responsive con breakpoints de Tailwind:

- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

## ⚡ Rendimiento

- **Code splitting** automático con Vite
- **Lazy loading** de imágenes
- **CSS purging** en producción (solo clases usadas)
- **Minificación** de JS y CSS
- **Tree shaking** para eliminar código no usado

## 🐛 Debugging

### Ver datos pasados a React

Abre la consola del navegador y escribe:

```javascript
window.MuseDockReact
```

Esto mostrará todos los datos pasados desde Blade.

### Logs en desarrollo

Los componentes loguean información útil en desarrollo:

```
MuseDock React Theme loaded successfully
{settings: {...}, menu: {...}, ...}
```

## 📚 Recursos

- [React Documentation](https://react.dev/)
- [TypeScript Handbook](https://www.typescriptlang.org/docs/)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Vite Guide](https://vitejs.dev/guide/)

## 🤝 Contribuir

Si encuentras bugs o quieres añadir características:

1. Reporta el issue en GitHub
2. Haz un fork del repositorio
3. Crea una branch para tu feature
4. Envía un pull request

## 📄 Licencia

Este tema es parte de MuseDock CMS. Ver licencia del proyecto principal.

---

**Desarrollado con ❤️ por el equipo de MuseDock**
