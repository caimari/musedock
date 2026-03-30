# 🚀 INSTAGRAM GALLERY - GUÍA DE INSTALACIÓN RÁPIDA

## 📖 ¿QUÉ SON LOS TOKENS DE 60 DÍAS?

### El Problema
Cuando conectas Instagram, recibes un **token de acceso** que te permite obtener fotos. Este token **expira en 60 días**.

**Sin auto-renovación:**
```
Día 1:  Conectas → Token válido por 60 días
Día 60: Token expira → ❌ Ya no puedes obtener fotos
        → Tienes que volver a conectar manualmente
```

### La Solución: Auto-Refresh
El módulo incluye un sistema que **renueva automáticamente** el token antes de que expire:

```
Día 1:   Conectas Instagram → Token expira en 60 días
Día 53:  Cron detecta "faltan 7 días" → ✅ Renueva automáticamente
         → Ahora el token expira en 60 días MÁS (día 113)
Día 106: Cron renueva otra vez → Token expira día 166
         → Y así INFINITAMENTE 🔄
```

**Con auto-renovación: Funciona PARA SIEMPRE sin intervención manual**

---

## 🔧 ¿PARA QUÉ SIRVE EL CRON JOB?

El **cron job** es un comando que se ejecuta **automáticamente cada día** a las 2 AM y hace:

1. ✅ Revisa todas las conexiones de Instagram
2. ✅ Busca tokens que expiran en **7 días o menos**
3. ✅ Los renueva automáticamente (extiende 60 días más)
4. ✅ Guarda un log de lo que hizo

**Sin cron:** Cada 60 días tienes que reconectar manualmente.
**Con cron:** El sistema se mantiene solo, para siempre.

---

## 📦 INSTALACIÓN DEL CRON EN UBUNTU

### Opción 1: Instalador Automático (Recomendado)

```bash
cd /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery
sudo bash install-cron.sh
```

El script te preguntará si quieres instalar y lo hará automáticamente.

### Opción 2: Manual

```bash
# Editar crontab
crontab -e

# Agregar esta línea al final:
0 2 * * * /usr/bin/php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php >> /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/cron.log 2>&1
```

**Explicación del comando:**
- `0 2 * * *` = Ejecutar todos los días a las 2:00 AM
- `/usr/bin/php` = Ejecutar con PHP
- `RefreshInstagramTokens.php` = Comando que renueva tokens
- `>> logs/cron.log` = Guardar resultado en archivo de log

### Verificar que Está Instalado

```bash
# Ver cron configurado
crontab -l

# Ver logs en tiempo real
tail -f /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/cron.log

# Probar manualmente
php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
```

---

## 🎯 CONFIGURACIÓN COMPLETA PASO A PASO

### PASO 1: Crear App en Facebook Developers

1. **Ir a:** https://developers.facebook.com/
2. **Hacer login** con tu cuenta de Facebook
3. **Click:** "My Apps" (Mis Apps)
4. **Click:** "Create App" (Crear App)
5. **Seleccionar:** "Consumer" (Consumidor)
6. **Click:** "Next"
7. **Llenar formulario:**
   - **App Display Name:** Instagram Gallery MuseDock
   - **App Contact Email:** tu@email.com
8. **Click:** "Create App"

### PASO 2: Agregar Instagram Basic Display

1. En el Dashboard de tu App, busca **"Instagram Basic Display"**
2. **Click:** "Set Up" (Configurar)
3. **Click:** "Create New App"
4. **Llenar:**
   - **Display Name:** Instagram Gallery
5. **Click:** "Create App"

### PASO 3: Configurar URLs de Callback

1. **Ir a:** "Basic Display" → "Settings"
2. **Llenar estos campos:**

   **Valid OAuth Redirect URIs** (poner AMBAS):
   ```
   https://tusitio.com/musedock/instagram/callback
   https://tusitio.com/admin/instagram/callback
   ```
   ⚠️ **IMPORTANTE:** Cambiar `tusitio.com` por tu dominio real
   ⚠️ **IMPORTANTE:** DEBE ser HTTPS (no HTTP)

   **Deauthorize Callback URL:**
   ```
   https://tusitio.com/musedock/instagram/deauthorize
   ```

   **Data Deletion Request URL:**
   ```
   https://tusitio.com/musedock/instagram/delete
   ```

3. **Click:** "Save Changes"

### PASO 4: Obtener Credenciales

1. En la pestaña **"Basic Display"**, verás:
   - **Instagram App ID:** `123456789012345`
   - **Instagram App Secret:** Click en "Show" para verlo

2. **Copiar ambos** (los necesitarás en el siguiente paso)

### PASO 5: Configurar en MuseDock (SuperAdmin)

1. **Login como SuperAdmin** en MuseDock
2. **Ir a:** `/musedock/instagram/settings`
3. **Pegar las credenciales:**
   - **Instagram App ID:** El que copiaste
   - **Instagram App Secret:** El que copiaste
   - **Redirect URI:** `https://tusitio.com/musedock/instagram/callback`
4. **Configurar opciones** (opcional):
   - Layout por defecto
   - Columnas
   - Número máximo de posts
5. **Click:** "Guardar"

### PASO 6: Conectar Instagram (SuperAdmin)

1. **Ir a:** `/musedock/instagram`
2. **Click:** "Conectar Nueva Cuenta"
3. **Te redirige a Instagram**
4. **Login con Instagram** (si no estás logeado)
5. **Click:** "Autorizar"
6. **Te regresa a MuseDock** con la cuenta conectada

### PASO 7: Sincronizar Posts

1. En `/musedock/instagram`, verás tu cuenta conectada
2. **Click:** botón "Sincronizar"
3. **Espera** unos segundos
4. **Verás:** "Sincronización completada: 25 posts actualizados"
5. **Click:** "Ver Posts" para verificar

### PASO 8: Usar en el Sitio

**Insertar shortcode** en cualquier página/post:

```
[instagram connection=1]
```

**Con opciones:**
```
[instagram connection=1 layout="masonry" columns=4 limit=12]
```

### PASO 9: Configurar Auto-Renovación (IMPORTANTE)

**Ubuntu/Linux:**
```bash
cd /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery
sudo bash install-cron.sh
```

✅ **¡LISTO!** Tu galería funcionará PARA SIEMPRE sin intervención.

---

## 👥 PARA TENANTS (Usuarios del Sistema)

Los tenants pueden conectar **sus propias cuentas** de Instagram:

1. **Login como Tenant**
2. **Ir a:** `/admin/instagram` (o buscar "Instagram Gallery" en el menú)
3. **Click:** "Conectar Nueva Cuenta"
4. **Autorizar en Instagram**
5. **Sincronizar posts**
6. **Usar shortcode:** `[instagram connection=1]`

⚠️ **NOTA:** El SuperAdmin debe haber configurado las credenciales de API primero.

---

## 📊 PARÁMETROS DEL SHORTCODE

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `connection` | int | - | ID de la conexión (requerido) |
| `username` | string | - | Alternativamente, usar username |
| `layout` | string | grid | grid, masonry, carousel, lightbox, justified |
| `columns` | int | 3 | Número de columnas (1-6) |
| `gap` | int | 10 | Espacio entre posts (px) |
| `limit` | int | 12 | Máximo de posts a mostrar |
| `show_caption` | bool | true | Mostrar descripciones |
| `caption_length` | int | 150 | Longitud máxima de caption |
| `lazy_load` | bool | true | Lazy loading de imágenes |

**Ejemplos:**

```
[instagram connection=1]
[instagram connection=1 layout="masonry"]
[instagram connection=1 layout="grid" columns=4 gap=15]
[instagram username="miusuario" limit=9]
[instagram connection=1 layout="carousel" show_caption=false]
```

---

## 🎨 LAYOUTS DISPONIBLES

### Grid (Cuadrícula)
```
[instagram connection=1 layout="grid" columns=3]
```
Disposición uniforme en filas y columnas iguales.

### Masonry (Pinterest)
```
[instagram connection=1 layout="masonry" columns=4]
```
Estilo Pinterest con alturas variables.

### Carousel (Slider)
```
[instagram connection=1 layout="carousel"]
```
Slider horizontal desplazable.

### Lightbox
```
[instagram connection=1 layout="lightbox" columns=4]
```
Miniaturas con vista ampliada al hacer clic.

### Justified
```
[instagram connection=1 layout="justified"]
```
Filas con altura uniforme y anchos variables.

---

## 🔍 VERIFICAR QUE TODO FUNCIONA

### 1. Verificar Cron Instalado
```bash
crontab -l | grep Instagram
```
Deberías ver la línea del cron.

### 2. Ver Estado de Tokens
Ir a `/musedock/instagram` y verificar:
- ✅ **Badge verde "Activa"** = Todo bien
- ⚠️ **Badge amarillo "Expira pronto"** = Se renovará pronto automáticamente
- ❌ **Badge rojo "Expirada"** = Debes reconectar

### 3. Ver Logs del Cron
```bash
cat /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/logs/cron.log
```

### 4. Probar Renovación Manual
```bash
php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
```

---

## 🆘 PROBLEMAS COMUNES

### "API no configurada"
❌ **Problema:** No has configurado Instagram App ID y Secret
✅ **Solución:** Ir a `/musedock/instagram/settings` y configurar

### "OAuth error: redirect_uri_mismatch"
❌ **Problema:** La URL de callback no coincide
✅ **Solución:** Verificar que en Facebook Developers tengas EXACTAMENTE:
   `https://tusitio.com/musedock/instagram/callback`

### "Token expirado"
❌ **Problema:** Han pasado 60 días sin renovar
✅ **Solución:**
   1. Desconectar la cuenta
   2. Volver a conectar
   3. Instalar el cron para que no vuelva a pasar

### "No se sincronizan posts"
❌ **Problema:** Token inválido o cuenta privada
✅ **Solución:**
   1. Verificar que la cuenta sea pública
   2. Reconectar la cuenta
   3. Intentar sincronizar de nuevo

### Cron no funciona
❌ **Problema:** Cron mal configurado o permisos
✅ **Solución:**
```bash
# Verificar que PHP esté en /usr/bin/php
which php

# Dar permisos
chmod +x /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php

# Probar manualmente
php /var/www/vhosts/musedock.net/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
```

---

## 📍 MENÚ EN EL SIDEBAR

El módulo automáticamente crea un ítem en el menú del admin:

**SuperAdmin:**
- 📍 Menú → Instagram Gallery → `/musedock/instagram`

**Tenant:**
- 📍 Menú → Instagram Gallery → `/admin/instagram`

Con icono de Instagram 📸

---

## 📞 SOPORTE

Si tienes problemas:
1. Revisar logs: `/modules/instagram-gallery/logs/`
2. Ver documentación completa: `README.md`
3. Verificar configuración en Facebook Developers

---

## ✅ CHECKLIST DE INSTALACIÓN

- [ ] Crear App en Facebook Developers
- [ ] Agregar Instagram Basic Display
- [ ] Configurar URLs de callback
- [ ] Copiar App ID y Secret
- [ ] Configurar en `/musedock/instagram/settings`
- [ ] Conectar cuenta de Instagram
- [ ] Sincronizar posts
- [ ] Probar shortcode en una página
- [ ] Instalar cron job (`install-cron.sh`)
- [ ] Verificar que el cron funciona

🎉 **¡Listo! Tu Instagram Gallery está funcionando.**
