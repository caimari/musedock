# Media Manager - Sistema de Gestión de Carpetas

## 📁 Descripción

El módulo Media Manager ahora incluye un completo sistema de gestión de archivos y carpetas similar a exploradores de archivos tradicionales. Permite organizar, navegar, mover y copiar archivos en una estructura jerárquica de carpetas.

## ✨ Funcionalidades

### Gestión de Carpetas
- ✅ **Crear carpetas**: Organiza tus archivos en carpetas y subcarpetas
- ✅ **Renombrar carpetas**: Cambia el nombre de las carpetas existentes
- ✅ **Eliminar carpetas**: Borra carpetas vacías
- ✅ **Navegación jerárquica**: Navega entre carpetas padre e hijas
- ✅ **Breadcrumbs**: Visualiza la ruta completa de navegación

### Operaciones con Archivos
- ✅ **Subir a carpetas**: Sube archivos directamente a carpetas específicas
- ✅ **Mover archivos**: Mueve archivos entre carpetas
- ✅ **Copiar archivos**: Duplica archivos a otras ubicaciones
- ✅ **Mover carpetas**: Reorganiza la estructura de carpetas
- ✅ **Eliminar archivos**: Borra archivos individuales

## 🚀 Instalación

### 1. Ejecutar Migración de Base de Datos

Ejecuta el script SQL correspondiente a tu base de datos:

**Para MySQL:**
```sql
-- Ejecutar la sección MySQL de:
modules/MediaManager/migrations/001_add_folders_support.sql
```

**Para PostgreSQL:**
```sql
-- Ejecutar la sección PostgreSQL de:
modules/MediaManager/migrations/001_add_folders_support.sql
```

La migración creará:
- Tabla `media_folders` para almacenar la estructura de carpetas
- Columna `folder_id` en la tabla `media`
- Carpetas raíz automáticas para tenants existentes

### 2. Verificar Rutas

Las siguientes rutas ya están definidas en `modules/MediaManager/routes.php`:

**Rutas de Carpetas:**
- `GET  /musedock/media/folders/structure` - Obtener estructura de carpetas
- `POST /musedock/media/folders/create` - Crear nueva carpeta
- `POST /musedock/media/folders/{id}/rename` - Renombrar carpeta
- `POST /musedock/media/folders/{id}/delete` - Eliminar carpeta

**Rutas de Operaciones:**
- `POST /musedock/media/move` - Mover archivos/carpetas
- `POST /musedock/media/copy` - Copiar archivos

### 3. Permisos de Directorios

Asegúrate de que el servidor web tenga permisos de escritura en:
```bash
chmod 755 /path/to/musedock/public/assets/uploads
chmod 755 /path/to/musedock/storage/app/public
```

## 📖 Uso de la API

### Obtener Estructura de Carpetas

```javascript
// Obtener carpeta raíz
fetch('/musedock/media/folders/structure')
    .then(response => response.json())
    .then(data => {
        console.log(data.current_folder); // Carpeta actual
        console.log(data.folders);         // Subcarpetas
        console.log(data.media);           // Archivos
        console.log(data.breadcrumbs);     // Ruta de navegación
    });

// Obtener carpeta específica
fetch('/musedock/media/folders/structure?folder_id=5')
    .then(response => response.json())
    .then(data => console.log(data));
```

### Crear Carpeta

```javascript
const formData = new URLSearchParams();
formData.append('name', 'Mi Nueva Carpeta');
formData.append('parent_id', '5'); // ID de la carpeta padre (opcional)
formData.append('description', 'Descripción opcional');
formData.append('_token', csrfToken);

fetch('/musedock/media/folders/create', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data.folder));
```

### Renombrar Carpeta

```javascript
const formData = new URLSearchParams();
formData.append('name', 'Nuevo Nombre');
formData.append('_token', csrfToken);

fetch('/musedock/media/folders/5/rename', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Eliminar Carpeta

```javascript
const formData = new URLSearchParams();
formData.append('_method', 'DELETE');
formData.append('_token', csrfToken);

fetch('/musedock/media/folders/5/delete', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

**Nota:** Solo se pueden eliminar carpetas vacías (sin archivos ni subcarpetas).

### Subir Archivo a Carpeta

```javascript
const formData = new FormData();
formData.append('file[]', fileObject);
formData.append('folder_id', '5'); // ID de la carpeta destino
formData.append('_token', csrfToken);

fetch('/musedock/media/upload', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Mover Archivos

```javascript
const formData = new URLSearchParams();
formData.append('item_ids[]', '10');
formData.append('item_ids[]', '11');
formData.append('item_ids[]', '12');
formData.append('item_type', 'media'); // 'media' o 'folder'
formData.append('target_folder_id', '5'); // null para raíz
formData.append('_token', csrfToken);

fetch('/musedock/media/move', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Copiar Archivos

```javascript
const formData = new URLSearchParams();
formData.append('media_ids[]', '10');
formData.append('media_ids[]', '11');
formData.append('target_folder_id', '5');
formData.append('_token', csrfToken);

fetch('/musedock/media/copy', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🏗️ Estructura de Modelos

### Modelo Folder

Ubicación: `modules/MediaManager/models/Folder.php`

**Métodos principales:**
- `parent()` - Obtiene la carpeta padre
- `children()` - Obtiene subcarpetas
- `media()` - Obtiene archivos de la carpeta
- `getBreadcrumbs()` - Obtiene ruta completa de navegación
- `canDelete()` - Verifica si se puede eliminar
- `countMediaRecursive()` - Cuenta archivos recursivamente
- `moveTo($parentId)` - Mueve carpeta a otro padre
- `getRootFolder($tenantId)` - Obtiene/crea carpeta raíz

### Modelo Media

Ubicación: `modules/MediaManager/models/Media.php`

**Nuevos métodos:**
- `folder()` - Obtiene la carpeta que contiene el archivo
- `moveToFolder($folderId)` - Mueve archivo a otra carpeta
- `copyToFolder($folderId)` - Copia archivo a otra carpeta

## 🎨 Ejemplo de Interfaz

La implementación de la interfaz de usuario puede ser personalizada según tus necesidades. Aquí un ejemplo básico:

```html
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb" id="folder-breadcrumbs"></ol>
</nav>

<!-- Barra de herramientas -->
<div class="toolbar">
    <button id="btn-new-folder">Nueva Carpeta</button>
    <button id="btn-upload">Subir Archivos</button>
    <button id="btn-move">Mover</button>
    <button id="btn-copy">Copiar</button>
</div>

<!-- Lista de carpetas y archivos -->
<div id="file-list" class="file-explorer">
    <!-- Se poblará dinámicamente con JavaScript -->
</div>
```

## 📝 Notas Importantes

1. **Carpetas Raíz**: Cada tenant tiene su propia carpeta raíz. No se pueden eliminar ni renombrar.

2. **Eliminación**: Solo se pueden eliminar carpetas que estén completamente vacías (sin archivos ni subcarpetas).

3. **Slugs**: Los nombres de carpetas se convierten automáticamente en slugs únicos para evitar conflictos.

4. **Rutas Físicas**: Las carpetas se crean físicamente en el sistema de archivos bajo:
   - `public/assets/uploads/[ruta-carpeta]`

5. **Multi-tenant**: El sistema soporta separación por tenant automáticamente.

## 🔧 Troubleshooting

### Error: "Carpeta no encontrada"
- Verifica que el `folder_id` sea correcto
- Asegúrate de que la carpeta pertenezca al tenant actual

### Error: "No se puede eliminar la carpeta"
- La carpeta debe estar vacía (sin archivos ni subcarpetas)
- No puedes eliminar la carpeta raíz

### Error: "No se puede crear carpeta"
- Verifica permisos de escritura en el directorio
- Comprueba que no exista una carpeta con el mismo nombre en esa ubicación

## 🚧 Próximas Mejoras

- [ ] Soporte para arrastrar y soltar archivos
- [ ] Vista de miniaturas/lista
- [ ] Búsqueda dentro de carpetas
- [ ] Etiquetas y metadatos adicionales
- [ ] Papelera de reciclaje
- [ ] Compartir carpetas entre tenants
- [ ] Compresión de carpetas (ZIP)

## 📄 Licencia

Este módulo es parte del sistema Musedock.

## 👥 Autor

Desarrollado para Musedock por el equipo de desarrollo.
