# Guía de Instalación Rápida - React Modern Theme

## 📋 Prerrequisitos

Antes de comenzar, asegúrate de tener instalado:

- ✅ **Node.js 18+** ([Descargar](https://nodejs.org/))
- ✅ **npm, yarn o pnpm** (viene con Node.js)
- ✅ **MuseDock CMS** funcionando correctamente
- ✅ Acceso SSH al servidor

## 🚀 Instalación Paso a Paso

### 1. Acceder al directorio del tema

```bash
cd /var/www/vhosts/musedock.net/httpdocs/themes/react-modern
```

### 2. Instalar dependencias Node.js

Elige tu gestor de paquetes favorito:

**Con npm:**
```bash
npm install
```

**Con yarn:**
```bash
yarn install
```

**Con pnpm (más rápido):**
```bash
pnpm install
```

Este proceso puede tardar 2-3 minutos la primera vez.

### 3. Compilar assets para producción

```bash
npm run build
```

Esto generará los archivos en la carpeta `dist/`:
- `dist/main.js` - React compilado
- `dist/style.css` - Tailwind CSS

### 4. Verificar que los archivos se generaron correctamente

```bash
ls -la dist/
```

Deberías ver:
```
total XX
drwxr-xr-x  2 user user 4096 ... .
drwxr-xr-x 10 user user 4096 ... ..
-rw-r--r--  1 user user XXXX ... main.js
-rw-r--r--  1 user user XXXX ... style.css
drwxr-xr-x  2 user user 4096 ... chunks
```

### 5. Crear enlace simbólico para assets

Para que los assets sean accesibles públicamente:

```bash
# Desde la raíz de MuseDock
cd /var/www/vhosts/musedock.net/httpdocs

# Crear directorio de assets si no existe
mkdir -p public/assets/themes

# Crear enlace simbólico
ln -s /var/www/vhosts/musedock.net/httpdocs/themes/react-modern public/assets/themes/react-modern
```

### 6. Activar el tema desde el panel de MuseDock

1. Accede al panel de administración: `https://musedock.net/musedock`
2. Ve a **Temas** en el menú lateral
3. Busca "React Modern"
4. Haz clic en **"Activar"**

### 7. ¡Listo! 🎉

Visita tu sitio en `https://musedock.net` y deberías ver el nuevo tema funcionando.

---

## 🔧 Solución de Problemas

### Problema: "Module not found" al compilar

**Solución:**
```bash
# Limpiar cache y reinstalar
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Problema: Los estilos no se cargan

**Verificar:**
1. Que `dist/style.css` existe y tiene contenido
2. Que el enlace simbólico está creado correctamente:
   ```bash
   ls -la /var/www/vhosts/musedock.net/httpdocs/public/assets/themes/react-modern
   ```
3. Limpiar caché del navegador (Ctrl + Shift + R)

### Problema: React no se carga (consola vacía)

**Verificar:**
1. Que `dist/main.js` existe
2. Abrir consola del navegador (F12) y buscar errores
3. Verificar que no hay errores de CORS o CSP

**Solución temporal:**
```bash
# Recompilar con sourcemaps para debugging
npm run build
```

### Problema: Permisos denegados

**Solución:**
```bash
# Dar permisos correctos
sudo chown -R www-data:www-data /var/www/vhosts/musedock.net/httpdocs/themes/react-modern/dist
sudo chmod -R 755 /var/www/vhosts/musedock.net/httpdocs/themes/react-modern/dist
```

### Problema: Menús no aparecen

**Verificar:**
1. Que tienes menús creados en el panel
2. Que los menús están asignados a ubicaciones ('nav', 'footer1', etc.)
3. Ver consola del navegador: `window.MuseDockReact.appData.menu`

---

## 🔄 Actualizaciones

Cuando actualices el código React o CSS:

```bash
# 1. Hacer cambios en src/
# 2. Recompilar
npm run build

# 3. Limpiar caché de navegador
# Ctrl + Shift + R (forzar recarga)
```

---

## 🎨 Personalización Rápida

### Cambiar colores del tema

Edita `tailwind.config.js`:

```javascript
theme: {
  extend: {
    colors: {
      primary: {
        DEFAULT: '#TU_COLOR',  // Cambiar aquí
        // ...
      }
    }
  }
}
```

Luego recompila: `npm run build`

### Añadir página al menú

1. Crea una página en el panel de MuseDock
2. Ve a **Menús** > **Editar menú**
3. Arrastra la página al menú
4. Guarda
5. ¡Aparecerá automáticamente en el header!

---

## 📞 Soporte

Si tienes problemas:

1. Revisa esta guía primero
2. Lee el `README.md` completo
3. Mira los logs de la consola del navegador
4. Revisa logs de PHP en `/storage/logs/`

---

**¡Disfruta de tu nuevo tema React Modern!** 🚀
