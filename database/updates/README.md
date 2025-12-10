# Actualizaciones de Base de Datos

Este directorio contiene scripts SQL para actualizar instalaciones existentes de MuseDock CMS.

## Actualización: Template y Hide Featured Image (2025-12-10)

### Archivos disponibles:

1. **`add_blog_fields_simple.sql`** - Script simple para phpMyAdmin
2. **`add_blog_template_and_hide_featured_image.sql`** - Script completo con verificaciones

### ¿Qué añade esta actualización?

Añade dos nuevos campos a la tabla `blog_posts`:

- **`template`**: Permite seleccionar una plantilla personalizada para cada post del blog
- **`hide_featured_image`**: Permite ocultar la imagen destacada en vistas públicas (listados y posts individuales)

### Instrucciones para instalar en phpMyAdmin:

1. Accede a phpMyAdmin
2. Selecciona tu base de datos (generalmente `musedocknet` o similar)
3. Ve a la pestaña **SQL**
4. Abre el archivo `add_blog_fields_simple.sql` y copia todo su contenido
5. Pégalo en el área de texto de phpMyAdmin
6. Haz clic en **Continuar** o **Go**

**Nota**: Si ves errores de "Duplicate column name", significa que los campos ya existen y puedes ignorarlos.

### Instrucciones para instalar desde línea de comandos:

```bash
# Opción 1: Script con verificaciones
mysql -u usuario -p nombre_base_datos < database/updates/add_blog_template_and_hide_featured_image.sql

# Opción 2: Script simple
mysql -u usuario -p nombre_base_datos < database/updates/add_blog_fields_simple.sql
```

### Verificar instalación:

```sql
-- Ejecuta esto en phpMyAdmin para verificar que los campos existen
DESCRIBE blog_posts;
```

Deberías ver los campos `template` y `hide_featured_image` en la lista.

---

## Notas para Desarrolladores

Las instalaciones nuevas ya incluyen estos campos en la migración principal:
- `/database/migrations/2025_01_01_000024_create_blog_posts_table.php`

Estos scripts son solo para actualizar instalaciones existentes.
