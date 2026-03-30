# Instagram Gallery Module 📸

Módulo completo de galería de Instagram para MuseDock con conexión directa a Instagram Basic Display API.

---

## 🚀 GUÍA RÁPIDA PARA EMPEZAR (5 MINUTOS)

### Paso 1: Crear App en Facebook Developers

1. **Ir a:** https://developers.facebook.com/
2. **Click en:** "My Apps" (Mis Apps) → "Create App" (Crear App)
3. **Seleccionar:** "Consumer" (Consumidor) → Click "Next"
4. **Llenar:**
   - **App Name:** Instagram Gallery MuseDock
   - **Email:** tu@email.com
5. **Click:** "Create App" (Crear App)

### Paso 2: Configurar Instagram Basic Display

1. En el Dashboard de tu App, busca **"Instagram Basic Display"**
2. **Click en:** "Set Up" (Configurar)
3. **Click en:** "Create New App"
4. **Ir a:** "Basic Display" → "Settings"
5. **Configurar:**

   **Valid OAuth Redirect URIs** (agregar estas 2 URLs):
   ```
   https://tusitio.com/musedock/instagram/callback
   https://tusitio.com/admin/instagram/callback
   ```

   **Deauthorize Callback URL:**
   ```
   https://tusitio.com/musedock/instagram/deauthorize
   ```

   **Data Deletion Request URL:**
   ```
   https://tusitio.com/musedock/instagram/delete
   ```

6. **Guardar cambios**

### Paso 3: Obtener Credenciales

1. En la pestaña **"Basic Display"**, copia:
   - **Instagram App ID** (ej: 123456789012345)
   - **Instagram App Secret** (ej: abc123def456...) - Click "Show"

### Paso 4: Configurar en MuseDock

1. **Ir a:** `/musedock/instagram/settings` (como SuperAdmin)
2. **Pegar:**
   - Instagram App ID
   - Instagram App Secret
   - Redirect URI: `https://tusitio.com/musedock/instagram/callback`
3. **Click:** "Guardar"

### Paso 5: Conectar Instagram

1. **Ir a:** `/musedock/instagram`
2. **Click:** "Conectar Nueva Cuenta"
3. **Autorizar** en Instagram
4. **Click:** "Sincronizar" para obtener posts

### Paso 6: Usar en tu Sitio

Inserta este shortcode donde quieras mostrar la galería:

```
[instagram connection=1]
```

O con opciones:
```
[instagram connection=1 layout="masonry" columns=4]
```

### Paso 7: Configurar Auto-Renovación (Opcional pero Recomendado)

Para que los tokens se renueven automáticamente cada 60 días:

**En Ubuntu/Linux:**
```bash
cd /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery
sudo bash install-cron.sh
```

**Manualmente:**
```bash
crontab -e
```

Agregar esta línea:
```
0 2 * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
```

✅ **¡Listo!** Tu galería de Instagram está funcionando.

---

## 📖 ¿CÓMO FUNCIONAN LOS TOKENS DE 60 DÍAS?

### El Problema
Instagram te da un **token de acceso** que dura **60 días**. Después de 60 días, el token expira y no puedes obtener más posts.

### La Solución Automática
El módulo incluye un sistema de **auto-renovación**:

1. **Día 1:** Conectas Instagram → Token expira en 60 días
2. **Día 53:** El cron job detecta que faltan 7 días
3. **Día 53:** Renueva automáticamente el token → Ahora expira en 60 días más
4. **Se repite infinitamente** 🔄

**Sin cron:** Tendrías que reconectar cada 60 días manualmente.
**Con cron:** Funciona **para siempre** automáticamente.

### Ver Estado de Tokens

En `/musedock/instagram` puedes ver:
- ✅ **Token activo** (verde)
- ⚠️ **Expira pronto** (amarillo) - faltan menos de 7 días
- ❌ **Token expirado** (rojo) - debes reconectar

---

## 🎯 Características

- ✅ **OAuth 2.0** - Conexión segura con Instagram Basic Display API
- ✅ **Multi-Tenant** - SuperAdmin y Tenants pueden conectar sus propias cuentas
- ✅ **Tokens de 60 días** - Gestión automática de tokens long-lived
- ✅ **Auto-refresh** - Renovación automática de tokens próximos a expirar
- ✅ **5 Layouts** - Grid, Masonry, Carousel, Lightbox, Justified
- ✅ **Shortcodes** - `[instagram connection=1]` para insertar galerías
- ✅ **Cache inteligente** - Posts almacenados en base de datos
- ✅ **MySQL + PostgreSQL** - Compatible con ambas bases de datos
- ✅ **SweetAlert2** - Modales y confirmaciones elegantes
- ✅ **Responsive** - Diseño adaptable a todos los dispositivos
- ✅ **i18n** - Traducido a español e inglés

## 📋 Requisitos

1. **Instagram Basic Display API**
   - App ID y App Secret de Facebook Developers
   - Redirect URI configurada

2. **Servidor**
   - PHP 7.4+
   - MySQL 5.7+ o PostgreSQL 12+
   - cURL habilitado
   - HTTPS (requerido por Instagram)

## 🚀 Instalación

### 1. Configurar Instagram Basic Display API

1. Ve a https://developers.facebook.com/
2. Crea una nueva App
3. Agrega el producto "Instagram Basic Display"
4. Configura OAuth Redirect URIs:
   ```
   https://tusitio.com/musedock/instagram/callback
   https://tusitio.com/admin/instagram/callback
   ```
5. Copia el App ID y App Secret

### 2. Configurar el Módulo

1. El módulo se auto-instala al cargarse (crea las tablas automáticamente)

2. Como SuperAdmin, ve a `/musedock/instagram/settings`

3. Configura las credenciales:
   - **Instagram App ID**: Tu App ID
   - **Instagram App Secret**: Tu App Secret
   - **Redirect URI**: `https://tusitio.com/musedock/instagram/callback`

### 3. Conectar Instagram

**SuperAdmin:**
1. Ve a `/musedock/instagram`
2. Click en "Conectar Nueva Cuenta"
3. Autoriza en Instagram
4. Sincroniza posts

**Tenant:**
1. Ve a `/admin/instagram`
2. Click en "Conectar Nueva Cuenta"
3. Autoriza en Instagram
4. Sincroniza posts

## 📖 Uso de Shortcodes

### Sintaxis Básica

```
[instagram connection=1]
```

### Con Parámetros

```
[instagram connection=1 layout="grid" columns=4 gap=15 limit=12]
```

### Por Username

```
[instagram username="miusuario" limit=9]
```

### Parámetros Disponibles

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `connection` | int | - | ID de la conexión |
| `username` | string | - | Username de Instagram |
| `layout` | string | grid | grid, masonry, carousel, lightbox, justified |
| `columns` | int | 3 | Número de columnas (1-6) |
| `gap` | int | 10 | Espacio entre posts en px |
| `limit` | int | 12 | Máximo de posts a mostrar |
| `show_caption` | bool | true | Mostrar descripciones |
| `caption_length` | int | 150 | Longitud máxima de caption |
| `lazy_load` | bool | true | Lazy loading de imágenes |
| `lightbox` | bool | true | Habilitar lightbox |

## 🎨 Layouts Disponibles

### Grid
Cuadrícula uniforme con filas y columnas iguales.

```
[instagram connection=1 layout="grid" columns=3]
```

### Masonry
Estilo Pinterest con alturas variables.

```
[instagram connection=1 layout="masonry" columns=4]
```

### Carousel
Slider horizontal desplazable.

```
[instagram connection=1 layout="carousel"]
```

### Lightbox
Miniaturas con vista ampliada al hacer clic.

```
[instagram connection=1 layout="lightbox" columns=4]
```

### Justified
Filas con altura uniforme y anchos variables.

```
[instagram connection=1 layout="justified"]
```

## ⚙️ Configuración

### Settings Globales (SuperAdmin)

**API Credentials:**
- `instagram_app_id`
- `instagram_app_secret`
- `instagram_redirect_uri`

**Display:**
- `default_layout` (grid)
- `default_columns` (3)
- `default_gap` (10)
- `max_posts_per_gallery` (50)

**Cache:**
- `cache_duration_hours` (6)
- `auto_refresh_tokens` (true)
- `token_refresh_threshold_days` (7)

**Visual:**
- `show_captions` (true)
- `caption_max_length` (150)
- `enable_lightbox` (true)
- `enable_lazy_loading` (true)
- `hover_effect` (zoom)
- `border_radius` (8)

## 🔄 Sincronización Automática

### Configurar Cron Job

Para renovar tokens automáticamente, configura este cron job:

```bash
# Ejecutar diariamente a las 2 AM
0 2 * * * php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
```

El comando:
- Busca tokens que expiran en los próximos 7 días
- Los renueva automáticamente (extiende 60 días más)
- Guarda logs en `modules/instagram-gallery/logs/`

### Sincronización Manual

Desde la interfaz:
- Click en botón "Sincronizar" en cualquier conexión
- Obtiene los últimos posts de Instagram
- Actualiza el cache local

## 📊 Base de Datos

### Tablas Creadas

**`instagram_connections`**
- Almacena conexiones de Instagram
- Tokens de acceso (encriptados)
- Información de expiración

**`instagram_posts`**
- Cache de posts de Instagram
- URLs de medios
- Metadata (caption, likes, comments)

**`instagram_settings`**
- Configuración global y por tenant
- Herencia de configuración

## 🔒 Seguridad

- ✅ Validación de OAuth state (CSRF protection)
- ✅ Tokens encriptados en base de datos
- ✅ Verificación de propiedad de conexiones
- ✅ SweetAlert2 para confirmaciones
- ✅ Sanitización de HTML en outputs

## 🐛 Troubleshooting

### "API no configurada"
→ Configura Instagram App ID y Secret en Settings

### "Token expirado"
→ Desconecta y vuelve a conectar la cuenta

### "No se sincronizan posts"
→ Verifica que el token no haya expirado
→ Revisa permisos en Facebook Developers

### "OAuth error"
→ Verifica que Redirect URI coincida exactamente
→ Asegúrate de usar HTTPS

## 📁 Estructura del Módulo

```
modules/instagram-gallery/
├── bootstrap.php               # Auto-carga y migración
├── helpers.php                 # Shortcodes y renderizado
├── routes.php                  # Rutas SuperAdmin y Tenant
├── module.json                 # Configuración del módulo
│
├── models/
│   ├── InstagramConnection.php # Gestión de conexiones
│   ├── InstagramPost.php       # Posts en cache
│   └── InstagramSetting.php    # Configuración
│
├── migrations/
│   └── 2025_12_14_000000_create_instagram_gallery_tables.php
│
├── services/
│   └── InstagramApiService.php # Instagram Basic Display API
│
├── commands/
│   └── RefreshInstagramTokens.php # Cron job
│
├── controllers/
│   ├── Superadmin/
│   │   ├── ConnectionController.php
│   │   └── SettingsController.php
│   └── Tenant/
│       ├── ConnectionController.php
│       └── GalleryController.php
│
├── views/
│   ├── superadmin/instagram/
│   │   ├── index.blade.php
│   │   ├── posts.blade.php
│   │   └── settings.blade.php
│   └── tenant/instagram/
│       ├── index.blade.php
│       ├── posts.blade.php
│       └── gallery.blade.php
│
├── lang/
│   ├── es.json
│   └── en.json
│
└── logs/                       # Logs de token refresh
```

## 🔗 Enlaces Útiles

- [Instagram Basic Display API Docs](https://developers.facebook.com/docs/instagram-basic-display-api)
- [Facebook App Dashboard](https://developers.facebook.com/apps/)
- [Instagram Graph API](https://developers.facebook.com/docs/instagram-api)

## 📝 Notas

- Los tokens duran **60 días** y se renuevan automáticamente
- Instagram limita a **100 posts** por request
- La API tiene rate limits (200 requests/hora)
- Solo funciona con cuentas **personales** (no Business con Basic Display)
- Para cuentas Business usa Instagram Graph API (requiere Facebook Page)

## 🎉 ¡Listo!

El módulo está 100% funcional y listo para usar.

**Próximos pasos:**
1. Configura las credenciales de API
2. Conecta tu cuenta de Instagram
3. Sincroniza posts
4. Inserta shortcodes en tu contenido
5. ¡Disfruta de tu galería de Instagram!
