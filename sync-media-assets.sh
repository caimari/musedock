#!/bin/bash
# Script para sincronizar assets del MediaManager a carpetas públicas

echo "🔄 Sincronizando assets de MediaManager..."

# Directorio fuente
SOURCE_JS="/var/www/vhosts/musedock.net/httpdocs/modules/media-manager/assets/js/"
SOURCE_CSS="/var/www/vhosts/musedock.net/httpdocs/modules/media-manager/assets/css/"

# Directorios destino
DEST_JS_1="/var/www/vhosts/musedock.net/httpdocs/public/assets/modules/MediaManager/js/"
DEST_JS_2="/var/www/vhosts/musedock.net/httpdocs/public/modules/MediaManager/assets/js/"
DEST_CSS_1="/var/www/vhosts/musedock.net/httpdocs/public/assets/modules/MediaManager/css/"
DEST_CSS_2="/var/www/vhosts/musedock.net/httpdocs/public/modules/MediaManager/assets/css/"

# Copiar archivos JS
echo "📄 Copiando archivos JavaScript..."
cp -v "${SOURCE_JS}"*.js "${DEST_JS_1}"
cp -v "${SOURCE_JS}"*.js "${DEST_JS_2}"

# Copiar archivos CSS
echo "🎨 Copiando archivos CSS..."
cp -v "${SOURCE_CSS}"*.css "${DEST_CSS_1}"
cp -v "${SOURCE_CSS}"*.css "${DEST_CSS_2}"

echo "✅ Sincronización completada"
echo ""
echo "📊 Archivos sincronizados:"
ls -lh "${DEST_JS_1}"
echo ""
ls -lh "${DEST_CSS_1}"
