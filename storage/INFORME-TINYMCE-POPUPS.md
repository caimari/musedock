# INFORME TÉCNICO: Bug de posicionamiento de popups y tooltips en TinyMCE

## Fecha: 10 de abril de 2026

---

## 1. DESCRIPCIÓN DEL BUG

Los menús desplegables (popups) de TinyMCE y los tooltips aparecen **desplazados hacia abajo** en lugar de posicionarse justo debajo del botón que los dispara. El desplazamiento es **proporcional a la posición de scroll** de la página: cuánto más se ha hecho scroll, más abajo aparece el popup.

### Elementos afectados:
- Botón del plugin **AI Writer** (menú desplegable)
- **Tooltips** de todos los botones de la toolbar de TinyMCE
- **Cualquier popup/menú** de TinyMCE (selectores de fuente, bloques, color, etc.)

---

## 2. DIAGNÓSTICO: CAUSA RAÍZ

### Arquitectura del layout AdminKit:

```
<body>                    ← sin scroll (window.scrollY = 0 siempre)
  <div class="wrapper">   ← flexbox horizontal (sidebar + main)
    <div class="main">    ← TIENE overflow: hidden (CSS AdminKit)
      <nav class="navbar">
      <main class="content">  ← TIENE overflow-y: auto → ESTE ES EL QUE HACE SCROLL
        ... formulario ...
        <div id="tinymce-skeleton">
        <textarea id="content-editor">
      </main>
    </div>
  </div>
  <!-- TinyMCE renderiza popups aquí (hijo directo de body) -->
  <div class="tox-tinymce-aux" style="position: absolute;">
</body>
```

### El problema:

1. **El scroll lo hace `.main > .content`**, NO la ventana (`window`). `window.scrollY` siempre es `0`.
2. TinyMCE (en modo `ui_mode: 'split'`) renderiza los popups como **`position: absolute`** dentro de `<body>`.
3. TinyMCE calcula la posición del botón con `getBoundingClientRect()`, que devuelve coordenadas relativas al **viewport**.
4. Pero al colocar el popup con `position: absolute` dentro de `<body>`, esas coordenadas se interpretan relativas al **offset del body**, no al viewport.
5. **Resultado**: cuando `.content` ha hecho scroll (ej: 500px), el botón está visualmente en su sitio pero `getBoundingClientRect()` ya no coincide con la posición `absolute` dentro del body.

**El AdminKit CSS** (archivo `app.css` servido en `/assets/superadmin/css/app.css`) define `.main` con `overflow: hidden` y `.content` con `overflow-y: auto`, creando un contexto de scroll interno que confunde el cálculo de posiciones de TinyMCE.

---

## 3. SOLUCIONES PROBADAS (sin éxito)

### 3.1 — `ui_mode: 'split'` + `ui_container: 'body'` (TinyMCE nativo)
- **Qué hace**: Renderiza popups como hijos de `<body>` en vez de dentro del editor.
- **Problema**: El popup se coloca con `position: absolute` en el body. Como el scroll lo hace `.content`, las coordenadas no coinciden.
- **Archivos**: `core/Views/Tenant/partials/_tinymce.blade.php`, `modules/blog/views/tenant/partials/_tinymce.blade.php`

### 3.2 — CSS `position: fixed` en `.tox-tinymce-aux`
- **Qué hace**: Forzar `position: fixed !important` en el contenedor de popups.
- **Problema**: TinyMCE calcula coordenadas como si fuera `position: absolute` (sumando `window.scrollY`). Como `window.scrollY` siempre es 0, pero el scroll real está en `.content`, el offset del scroll se suma incorrectamente.
- **Resultado**: El popup aparece desplazado hacia abajo exactamente por la cantidad de scroll de `.content`.

### 3.3 — JS compensatorio `translateY(-scrollY)`
- **Qué hace**: Aplicar `transform: translateY(-window.scrollY)` al contenedor de popups.
- **Problema**: `window.scrollY` es 0 (el scroll no es de window), así que no compensa nada.

### 3.4 — JS `updateTinyAuxOffset` (compensando scroll del contenedor)
- **Qué hace**: Calcula `containerScroll - windowScroll` y lo aplica como CSS variable.
- **Problema**: Solo funciona parcialmente; los tooltips internos de TinyMCE recalculan posiciones internamente y el parche no les afecta.

### 3.5 — Forzar `overflow: visible` en `.wrapper` y `.main` (JS)
- **Qué hace**: JS que periódicamente fuerza `overflow: visible` en los contenedores.
- **Problema**: El scroll deja de funcionar correctamente o AdminKit lo reestablece.

### 3.6 — `MutationObserver` para reubicar `.tox-tinymce-aux` en `<body>`
- **Qué hace**: Mueve el contenedor de popups al body si no está ahí.
- **Problema**: Ya estaba en el body; el problema no es dónde está sino cómo se calculan las posiciones.

---

## 4. SOLUCIÓN QUE DEBERÍA FUNCIONAR (no implementada aún)

### Opción A: Cambiar la arquitectura de scroll del layout

Hacer que **la ventana (body) sea la que haga scroll** en lugar de `.content`, manteniendo el sidebar fijo con `position: fixed` o `sticky`.

**Cambios necesarios:**

```css
/* En app.blade.php o override CSS */
html, body {
    overflow-y: auto !important;
    height: auto !important;
}
.wrapper {
    overflow: visible !important;
    height: auto !important;
}
.main {
    overflow: visible !important;
    height: auto !important;
}
.content {
    overflow-y: visible !important;  /* Que el body haga el scroll, no .content */
    height: auto !important;
}
```

Y hacer el sidebar `position: sticky` o `fixed` para que no se desplace.

**Riesgo**: Puede afectar el comportamiento general del panel de administración (AdminKit). Requiere testing exhaustivo del layout.

**Archivo**: `core/Views/Tenant/layouts/app.blade.php` (líneas con CSS inline y estructura HTML)

### Opción B: Monkey-patch TinyMCE para corregir posiciones

Interceptar la posición de cada popup/tooltip cuando se abre, leyendo `scrollTop` del contenedor `.content` y restando ese valor a la posición `top` calculada por TinyMCE.

**Código propuesto:**

```javascript
// Añadir en setup() de TinyMCE, después de editor.on('init')
editor.on('init', function() {
    // Corregir posición de todos los popups de TinyMCE
    var scrollContainer = document.querySelector('.main > .content') 
                       || document.querySelector('.content');
    
    if (scrollContainer) {
        // Observar cuando se añaden popups al DOM
        var auxObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Corregir posición de popups (tox-menu, tox-tooltip, etc.)
                        correctPopupPosition(node);
                        // También corregir hijos
                        node.querySelectorAll && node.querySelectorAll('.tox-menu, .tox-tooltip, .tox-dialog-wrap').forEach(correctPopupPosition);
                    }
                });
            });
        });
        
        auxObserver.observe(document.body, { childList: true, subtree: true });
        
        function correctPopupPosition(el) {
            var currentTop = parseInt(el.style.top) || 0;
            var containerScroll = scrollContainer.scrollTop;
            if (containerScroll > 0 && currentTop > 0) {
                el.style.top = (currentTop - containerScroll) + 'px';
            }
        }
    }
});
```

**Riesgo**: Puede parpadear (el popup aparece mal posicionado un frame antes de corregirse). Se puede mitigar con `visibility: hidden` inicial y transición.

### Opción C: Usar `position: fixed` + patch de coordenadas

Forzar `position: fixed` en `.tox-tinymce-aux` e interceptar `window.scrollTo` o el scroll de `.content` para compensar. Requiere patchear el método interno de TinyMCE que calcula posiciones.

```javascript
// Sobrescribir getBoundingClientRect del editor container para compensar scroll
var originalGetBoundingClientRect = HTMLElement.prototype.getBoundingClientRect;
// NO recomendado: afecta a TODOS los elementos de la página
```

**Riesgo alto**: No recomendado.

### Opción D (RECOMENDADA): Reestructurar scroll + TinyMCE inline

1. Cambiar el layout para que `body` haga el scroll (Opción A)
2. O BIEN: Usar TinyMCE en modo `inline` (sin iframe) que no tiene estos problemas de posicionamiento

---

## 5. ARCHIVOS IMPLICADOS

| Archivo | Ruta | Rol |
|---------|------|-----|
| Layout principal tenant | `core/Views/Tenant/layouts/app.blade.php` | Define estructura HTML con `.wrapper > .main > .content` y CSS inline de scroll |
| TinyMCE partial (core) | `core/Views/Tenant/partials/_tinymce.blade.php` | Configuración de TinyMCE para páginas del core |
| TinyMCE partial (blog) | `modules/blog/views/tenant/partials/_tinymce.blade.php` | Configuración de TinyMCE para el módulo blog |
| TinyMCE partial (superadmin) | `core/Views/Superadmin/partials/_tinymce.blade.php` | Configuración de TinyMCE para superadmin (no afectado) |
| AI Writer plugin | `public/modules/aiwriter/js/tiny-ai-plugin.js` | Plugin de TinyMCE para AI Writer |
| AdminKit CSS | `/assets/superadmin/css/app.css` (referenciado, puede ser compilado) | CSS del framework AdminKit con `overflow: hidden` en `.main` |
| Header/Sidebar | `themes/default/views/partials/header-sidebar.blade.php` | Sidebar del panel |

---

## 6. ESTADO ACTUAL DE LA CONFIGURACIÓN

### `core/Views/Tenant/partials/_tinymce.blade.php`:
- `ui_mode: 'split'` ✅
- `ui_container: 'body'` ✅
- CSS `.tox-tinymce-aux`: solo `z-index: 100002 !important` (sin position:fixed) ✅
- JS parche `updateAuxTransform`: ELIMINADO ✅
- Mantiene funciones `ensureTinyAuxLayerOnBody()` y `startTinyAuxObserver()` para mover popups al body

### `modules/blog/views/tenant/partials/_tinymce.blade.php`:
- `ui_mode: 'split'` ✅ (recién añadido)
- `ui_container: 'body'` ✅ (recién añadido)
- Sin hacks CSS/JS adicionales ✅

### `core/Views/Tenant/layouts/app.blade.php`:
- CSS inline que fuerza `overflow-y: auto` en `.content` 
- JS `fixTinyMCEPopups()` que fuerza `overflow: visible` en `.wrapper` y `.main`
- **El scroll real lo hace `.content`**, no la ventana

---

## 7. COMPROBACIÓN RÁPIDA PARA EL TÉCNICO

1. Abrir el panel de administración como tenant
2. Ir a editar un post del blog
3. Hacer scroll hacia abajo en la página
4. Hacer clic en cualquier botón de la toolbar de TinyMCE (ej: selector de bloques, color, o AI Writer)
5. **Bug**: El menú desplegable aparece más abajo de donde debería, proporcional al scroll
6. Los tooltips también aparecen desplazados

### Herramientas de debug en consola del navegador:

```javascript
// Verificar qué elemento hace el scroll
document.querySelector('.content').scrollTop  // → Este tendrá el valor del scroll
window.scrollY  // → Siempre 0 (este es el problema)

// Verificar dónde está el contenedor de popups
document.querySelector('.tox-tinymce-aux').parentElement  // → Debería ser <body>
document.querySelector('.tox-tinymce-aux').style.position  // → "absolute"
```

---

## 8. RESUMEN EJECUTIVO

**El bug es causado por un conflicto entre la arquitectura de scroll del layout AdminKit (scroll interno en `.content`) y el sistema de posicionamiento de popups de TinyMCE (que asume que el scroll es de la ventana).**

La solución más limpia es **cambiar el layout para que la ventana haga el scroll** (con sidebar sticky/fixed), o bien **implementar un monkey-patch JS** que corrija las posiciones de los popups restando el `scrollTop` del contenedor `.content`.